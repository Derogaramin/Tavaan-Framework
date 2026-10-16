<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Failed;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Queue\FailedJobProviderInterface;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Queue
 * DatabaseFailedJobProvider — ثبت jobهای شکست‌خورده در tvn_failed_jobs (DOC-061 §5).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class DatabaseFailedJobProvider implements FailedJobProviderInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table = 'tvn_failed_jobs',
    ) {
    }

    public function log(string $connection, string $queue, string $payload, \Throwable $e, int|string|null $id = null): string
    {
        $decoded = \json_decode($payload, true);
        $uuid = \is_array($decoded) && \is_string($decoded['uuid'] ?? null) && $decoded['uuid'] !== ''
            ? $decoded['uuid']
            : (string) Ulid::generate();

        $this->db->table($this->table)->insert([
            'uuid' => $uuid,
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => $this->renderException($e),
            'failed_at' => Clock::now()->format('Y-m-d H:i:s.v'),
        ]);

        return $uuid;
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit = 100): array
    {
        return $this->db->table($this->table)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, mixed>|null */
    public function find(int|string $idOrUuid): ?array
    {
        $query = $this->db->table($this->table);
        if (\is_string($idOrUuid) && !\ctype_digit($idOrUuid)) {
            $query->where('uuid', $idOrUuid);
        } else {
            $query->where('id', $idOrUuid);
        }

        return $query->first();
    }

    public function forget(int|string $idOrUuid): bool
    {
        $query = $this->db->table($this->table);
        if (\is_string($idOrUuid) && !\ctype_digit($idOrUuid)) {
            $query->where('uuid', $idOrUuid);
        } else {
            $query->where('id', $idOrUuid);
        }

        return $query->delete() > 0;
    }

    public function flush(?int $hours = null): int
    {
        $query = $this->db->table($this->table);

        if ($hours !== null && $hours > 0) {
            $cutoff = Clock::now()->sub(new \DateInterval('PT' . $hours . 'H'))->format('Y-m-d H:i:s.v');
            $query->where('failed_at', '<', $cutoff);
        }

        return $query->delete();
    }

    public function count(): int
    {
        return $this->db->table($this->table)->count();
    }

    /** 🔒 متن استثنا بدون نشت دادهٔ حساس (message + trace خلاصه). */
    private function renderException(\Throwable $e): string
    {
        $out = $e::class . ': ' . $e->getMessage() . "\n";
        $frames = \array_slice(\explode("\n", $e->getTraceAsString()), 0, 25);
        $out .= \implode("\n", $frames);

        return $out;
    }
}
