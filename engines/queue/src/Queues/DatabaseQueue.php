<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Queues;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Queue\JobInterface;
use Tavaan\Engine\Queue\Jobs\DatabaseJob;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Queue
 * DatabaseQueue — درایور database (پیش‌فرض بدون Redis؛ DOC-061 §6).
 *
 * 🔒 Pop اتمیک: تراکنش + FOR UPDATE SKIP LOCKED → دو worker موازی
 * هرگز یک job را دوبار پردازش نمی‌کنند (در SQLite خودِ تراکنش اتمیک است).
 * jobهای reserved مانده (> retry_after) به‌چرخه برمی‌گردند.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class DatabaseQueue extends Queue
{
    private readonly string $table;

    private readonly int $retryAfter;

    /** @param array<string, mixed> $config */
    public function __construct(
        string $connectionName,
        array $config,
        \Tavaan\Engine\Queue\Serializer $serializer,
        private readonly ConnectionInterface $db,
        ?\Tavaan\Core\Contracts\Container\ContainerInterface $container = null,
    ) {
        parent::__construct($connectionName, $config, $serializer, $container);

        $this->table = (string) ($config['table'] ?? 'tvn_jobs');
        $this->retryAfter = (int) ($config['retry_after'] ?? 90);
    }

    public function pushRaw(string $payload, ?string $queue = null): int|string|null
    {
        return $this->insert($queue ?? $this->queueFromPayload($payload), Clock::timestamp(), $payload);
    }

    public function laterRaw(int $availableAt, string $payload, ?string $queue = null): int|string|null
    {
        return $this->insert($queue ?? $this->queueFromPayload($payload), $availableAt, $payload);
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        $queue ??= $this->defaultQueue();
        $now = Clock::timestamp();
        $retryBefore = $now - $this->retryAfter;

        return $this->db->transaction(function () use ($queue, $now, $retryBefore): ?JobInterface {
            $row = $this->db->table($this->table)
                ->where('queue', $queue)
                ->where('available_at', '<=', $now)
                ->where(static function ($q) use ($retryBefore): void {
                    // آزاد یا reserved گیرکرده (worker مرده) → قابل برداشت مجدد
                    $q->whereNull('reserved_at')
                        ->orWhere('reserved_at', '<=', $retryBefore);
                })
                ->orderBy('id')
                ->forUpdateSkipLocked()   // 🔒 workerهای موازی
                ->first();

            if ($row === null) {
                return null;
            }

            $attempts = (int) $row['attempts'] + 1;
            $this->db->table($this->table)
                ->where('id', $row['id'])
                ->update(['reserved_at' => $now, 'attempts' => $attempts]);

            return new DatabaseJob(
                $this->container,
                $this->connectionName,
                $queue,
                (string) $row['payload'],
                (int) $row['id'],
                $attempts,
                $this,
            );
        });
    }

    public function size(?string $queue = null): int
    {
        $query = $this->db->table($this->table);
        if ($queue !== null) {
            $query->where('queue', $queue);
        }

        return $query->count();
    }

    /** حذف job پس از پردازش موفق (یا شکست نهایی). */
    public function deleteReserved(int $id): void
    {
        $this->db->table($this->table)->where('id', $id)->delete();
    }

    /** برگرداندن job به صف با تأخیر (retry). */
    public function releaseReserved(int $id, int $delay): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->update(['reserved_at' => null, 'available_at' => Clock::timestamp() + $delay]);
    }

    /** همهٔ jobهای یک صف (برای مانیتورینگ). */
    public function clear(?string $queue = null): int
    {
        $query = $this->db->table($this->table);
        if ($queue !== null) {
            $query->where('queue', $queue);
        }

        return $query->delete();
    }

    private function insert(string $queue, int $availableAt, string $payload): int
    {
        $now = Clock::timestamp();

        return (int) $this->db->table($this->table)->insertGetId([
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => $now,
        ]);
    }

    private function queueFromPayload(string $payload): string
    {
        $decoded = \json_decode($payload, true);

        return \is_array($decoded) && \is_string($decoded['queue'] ?? null) && $decoded['queue'] !== ''
            ? $decoded['queue']
            : $this->defaultQueue();
    }
}
