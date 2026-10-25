<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * OutboxPublisher — Transactional Outbox (ADR-0009 / AC-ERP-11).
 */

namespace Modules\Erp\Infrastructure\Integration;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * OutboxPublisher — رویدادهای یکپارچگی ERP **درون همان تراکنش کسب‌وکار**
 * در `tvn_outbox` ثبت می‌شوند؛ پس از commit با `flushNow()` به‌صورت درون‌خطی
 * رله می‌شوند (best-effort). در production می‌توان رله را به کرون
 * `event:outbox-relay` سپرد — بدون تغییر کد ماژول.
 *
 * 🔒 بدون نشت: اگر فرایند پس از commit و پیش از رله بمیرد، ردیف pending
 * می‌ماند و relay بعدی آن را منتشر می‌کند (دفتر کل همیشه درست می‌ماند).
 *
 * @api 1.0
 * @see docs/adr/ADR-0009-outbox-pattern.md
 */
final class OutboxPublisher
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    /**
     * ثبت رویداد در outbox — باید درون تراکنش کسب‌وکار صدا زده شود.
     *
     * رویداد دامنه باید `eventName()` و `toPayload()` داشته باشد.
     */
    public function store(object $domainEvent): string
    {
        /** @var string $name */
        $name = method_exists($domainEvent, 'eventName') ? $domainEvent->eventName() : '';
        /** @var array<string, mixed> $payload */
        $payload = method_exists($domainEvent, 'toPayload') ? $domainEvent->toPayload() : [];

        if ($name === '') {
            throw new \DomainException('رویداد بدون نام در outbox ثبت نمی‌شود.');
        }

        $eventId = Ulid::generate(Clock::utcNow())->toString();

        $this->db->table($this->table())->insert([
            'event_id' => $eventId,
            'event_name' => $name,
            'payload' => (string) \json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'headers' => (string) \json_encode(['source' => 'tavaan.erp'], JSON_THROW_ON_ERROR),
            'trace_id' => null,
            'status' => 'pending',
            'owner' => null,
            'attempts' => 0,
            'available_at' => Clock::timestamp(),
            'claimed_at' => null,
            'processed_at' => null,
            'created_at' => Clock::utcNow()->format('Y-m-d H:i:s.u'),
        ]);

        return $eventId;
    }

    /**
     * رلهٔ درون‌خطی — fetch pending → dispatch → markDone (best-effort).
     *
     * @return int تعداد رویدادهای منتشرشده
     */
    public function flushNow(int $limit = 200): int
    {
        if (!Config::get('erp.integration.inline_relay', true)) {
            return 0;
        }

        $table = $this->table();
        $owner = \bin2hex(\random_bytes(8));
        $now = Clock::timestamp();

        // claim اتمیک (همسان با DatabaseOutbox موتور event)
        $candidates = $this->db->table($table)
            ->where('status', 'pending')
            ->where('available_at', '<=', $now)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($candidates === []) {
            return 0;
        }

        $this->db->table($table)
            ->whereIn('id', $candidates)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'owner' => $owner,
                'claimed_at' => Clock::utcNow()->format('Y-m-d H:i:s.u'),
            ]);

        $rows = $this->db->table($table)
            ->whereIn('id', $candidates)
            ->where('owner', $owner)
            ->where('status', 'processing')
            ->orderBy('id')
            ->get();

        $published = 0;
        foreach ($rows as $row) {
            $name = (string) ($row['event_name'] ?? '');
            $payload = [];
            $raw = $row['payload'] ?? null;
            if (\is_string($raw) && $raw !== '') {
                $decoded = \json_decode($raw, true);
                $payload = \is_array($decoded) ? $decoded : [];
            }

            try {
                Event::dispatch($name, $payload);
                $this->db->table($table)->where('id', $row['id'])->update([
                    'status' => 'done',
                    'processed_at' => Clock::utcNow()->format('Y-m-d H:i:s.u'),
                ]);
                ++$published;
            } catch (\Throwable) {
                // شکست مصرف‌کننده → تلاش مجدد؛ پس از سقف → failed (همسان با موتور)
                $attempts = (int) ($row['attempts'] ?? 0) + 1;
                $this->db->table($table)->where('id', $row['id'])->update([
                    'status' => $attempts >= 5 ? 'failed' : 'pending',
                    'owner' => null,
                    'attempts' => $attempts,
                ]);
            }
        }

        return $published;
    }

    /** تعداد رویدادهای در انتظار (داشبورد/تست AC-ERP-11). */
    public function pendingCount(): int
    {
        return (int) $this->db->table($this->table())->where('status', 'pending')->count();
    }

    private function table(): string
    {
        return (string) Config::get('erp.integration.outbox_table', 'tvn_outbox');
    }
}
