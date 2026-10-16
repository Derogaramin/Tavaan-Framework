<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\Brokers;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Realtime\BrokerInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Realtime
 * DatabaseBroker — broker با polling روی جدول tvn_broadcast_events.
 *
 * مناسب استقرار بدون Redis؛ رویدادهای قدیمی براساس TTL پاک می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class DatabaseBroker implements BrokerInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'tvn_broadcast_events',
        private readonly int $ttlSeconds = 3600,
        private readonly int $maxPerPoll = 200,
    ) {
    }

    public function publish(string $channel, string $name, array $payload): void
    {
        $this->connection->table($this->table)->insert([
            'channel' => $channel,
            'name' => $name,
            'payload' => (string) \json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'created_at' => Clock::isoNow(),
        ]);

        // پاک‌سازی فرصت‌طلبانهٔ رویدادهای کهنه (هر ~۵۰ انتشار یک‌بار)
        if (\random_int(1, 50) === 1) {
            $this->prune();
        }
    }

    public function poll(array $channels, int $timeout = 1, int $afterId = 0): array
    {
        if ($channels === []) {
            return [];
        }

        $deadline = \microtime(true) + \max(0, $timeout);
        do {
            $rows = $this->connection->table($this->table)
                ->whereIn('channel', $channels)
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($this->maxPerPoll)
                ->get();

            if ($rows !== [] || \microtime(true) >= $deadline) {
                break;
            }
            \usleep(50_000);   // ۵۰ms — polling ملایم
        } while (true);

        $out = [];
        foreach ($rows as $row) {
            $payload = \json_decode((string) ($row['payload'] ?? '{}'), true);
            $out[] = [
                'id' => (int) $row['id'],
                'channel' => (string) $row['channel'],
                'name' => (string) $row['name'],
                'payload' => \is_array($payload) ? $payload : [],
                'at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    public function lastId(): int
    {
        $row = $this->connection->table($this->table)->orderByDesc('id')->limit(1)->first();

        return (int) ($row['id'] ?? 0);
    }

    public function name(): string
    {
        return 'database';
    }

    public function prune(): int
    {
        // 🔒 cutoff با همان قالب Clock::isoNow (مقایسهٔ رشته‌ای ISO ترتیب زمانی دارد)
        $cutoff = (new \DateTimeImmutable('-' . $this->ttlSeconds . ' seconds'))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.uP');

        return (int) $this->connection->table($this->table)->where('created_at', '<', $cutoff)->delete();
    }
}
