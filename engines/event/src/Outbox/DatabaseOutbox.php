<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event\Outbox;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Event
 * DatabaseOutbox — الگوی Outbox (ADR-0009): رویداد درون **همان تراکنش**
 * کسب‌وکار در جدول outbox ثبت می‌شود؛ Relay پس از commit آن را منتشر می‌کند.
 *
 * 🔒 claim با UPDATE اتمیک (owner token) — سازگار با MySQL/PG/SQLite؛
 *    در MySQL/PG می‌توان FOR UPDATE SKIP LOCKED را در لایهٔ relay افزود.
 *
 * @api 1.0
 * @see docs/adr/ADR-0009-outbox-pattern.md
 */
final class DatabaseOutbox
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table = 'tvn_outbox',
    ) {
    }

    /**
     * ذخیرهٔ رویداد (باید درون تراکنش کسب‌وکار صدا زده شود).
     *
     * @param array<string, mixed>|object $payload
     */
    public function store(string $event, mixed $payload = [], array $headers = []): string
    {
        $serialized = \is_string($payload) ? $payload : (string) \json_encode(
            \is_object($payload) ? ['class' => $payload::class] : $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        $eventId = \Tavaan\Support\Ulid::generate()->toString();

        $this->db->table($this->table)->insert([
            'event_id' => $eventId,
            'event_name' => $event,
            'payload' => $serialized,
            'headers' => (string) \json_encode($headers, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'owner' => null,
            'attempts' => 0,
            'available_at' => Clock::timestamp(),
            'created_at' => Clock::utcNow()->format('Y-m-d H:i:s.u'),
        ]);

        return $eventId;
    }

    /**
     * برداشتن دسته‌ای از رویدادهای آماده (claim اتمیک).
     *
     * @return list<array<string, mixed>>
     */
    public function fetchPending(int $limit = 100, string $owner = ''): array
    {
        $owner = $owner !== '' ? $owner : \bin2hex(\random_bytes(8));
        $now = Clock::timestamp();

        // انتخاب کاندیدها
        $candidates = $this->db->table($this->table)
            ->where('status', 'pending')
            ->where('available_at', '<=', $now)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($candidates === []) {
            return [];
        }

        // 🔒 claim اتمیک: فقط ردیف‌های هنوز pending به این owner می‌چسبند
        $this->db->table($this->table)
            ->whereIn('id', $candidates)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'owner' => $owner,
                'claimed_at' => Clock::utcNow()->format('Y-m-d H:i:s.u'),
            ]);

        return $this->db->table($this->table)
            ->whereIn('id', $candidates)
            ->where('owner', $owner)
            ->where('status', 'processing')
            ->orderBy('id')
            ->get();
    }

    public function markDone(int|string $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->update(['status' => 'done', 'processed_at' => Clock::utcNow()->format('Y-m-d H:i:s.u')]);
    }

    /** شکست → تلاش مجدد با backoff یا failed. */
    public function markFailed(int|string $id, int $maxAttempts = 5): void
    {
        $row = $this->db->table($this->table)->where('id', $id)->first();
        $attempts = (int) ($row['attempts'] ?? 0) + 1;

        if ($attempts >= $maxAttempts) {
            $this->db->table($this->table)->where('id', $id)->update(['status' => 'failed', 'attempts' => $attempts]);

            return;
        }

        $backoff = 2 ** $attempts;   // 2,4,8,16,… ثانیه
        $this->db->table($this->table)->where('id', $id)->update([
            'status' => 'pending',
            'attempts' => $attempts,
            'available_at' => Clock::timestamp() + $backoff,
            'owner' => null,
        ]);
    }

    /** آزادسازی claimهای گیرکرده (crash recovery). */
    public function releaseStale(int $olderThanSeconds = 300): int
    {
        return $this->db->table($this->table)
            ->where('status', 'processing')
            ->where('claimed_at', '<', Clock::utcNow()->sub(new \DateInterval('PT' . $olderThanSeconds . 'S'))->format('Y-m-d H:i:s.u'))
            ->update(['status' => 'pending', 'owner' => null]);
    }

    public function pendingCount(): int
    {
        return $this->db->table($this->table)->where('status', 'pending')->count();
    }
}
