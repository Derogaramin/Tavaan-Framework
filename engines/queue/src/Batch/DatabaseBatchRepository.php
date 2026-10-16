<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Batch;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Queue\BatchRepositoryInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Queue
 * DatabaseBatchRepository — مخزن batch روی جدول tvn_job_batches (DOC-061 §5).
 *
 * 🔒 شمارش‌ها با UPDATE اتمیک (`pending_jobs - 1`) — workerهای موازی امن.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class DatabaseBatchRepository implements BatchRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table = 'tvn_job_batches',
    ) {
    }

    /** @param array<string, mixed> $options */
    public function create(string $id, int $totalJobs, array $options): void
    {
        $now = Clock::timestamp();

        $this->db->table($this->table)->insert([
            'id' => $id,
            'name' => (string) ($options['name'] ?? ''),
            'total_jobs' => $totalJobs,
            'pending_jobs' => $totalJobs,
            'failed_jobs' => 0,
            'failed_job_ids' => \json_encode([], \JSON_THROW_ON_ERROR),
            'options' => \json_encode($options, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            'cancelled_at' => null,
            'created_at' => $now,
            'finished_at' => null,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $row = $this->db->table($this->table)->where('id', $id)->first();

        return $row === null ? null : $this->decode($row);
    }

    public function recordSuccess(string $id, string $jobUuid): bool
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->where('pending_jobs', '>', 0)
            ->decrement('pending_jobs');

        $record = $this->find($id);

        return $record !== null && (int) $record['pending_jobs'] === 0;
    }

    public function recordFailure(string $id, string $jobUuid, bool $allowFailures): bool
    {
        $this->db->transaction(function () use ($id, $jobUuid): void {
            $row = $this->db->table($this->table)->where('id', $id)->forUpdate()->first();
            if ($row === null) {
                return;
            }

            $ids = \json_decode((string) ($row['failed_job_ids'] ?? '[]'), true);
            $ids = \is_array($ids) ? $ids : [];
            $ids[] = $jobUuid;

            $this->db->table($this->table)->where('id', $id)->update([
                'pending_jobs' => \max(0, (int) $row['pending_jobs'] - 1),
                'failed_jobs' => (int) $row['failed_jobs'] + 1,
                'failed_job_ids' => \json_encode($ids, \JSON_THROW_ON_ERROR),
            ]);
        });

        $record = $this->find($id);

        return $record !== null && (int) $record['pending_jobs'] === 0;
    }

    public function cancel(string $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => Clock::timestamp()]);
    }

    public function finish(string $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->whereNull('finished_at')
            ->update(['finished_at' => Clock::timestamp()]);
    }

    public function delete(string $id): void
    {
        $this->db->table($this->table)->where('id', $id)->delete();
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit = 50): array
    {
        $rows = $this->db->table($this->table)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return \array_map(fn (array $row): array => $this->decode($row), $rows);
    }

    /** @param array<string, mixed> $row */
    private function decode(array $row): array
    {
        $ids = \json_decode((string) ($row['failed_job_ids'] ?? '[]'), true);
        $options = \json_decode((string) ($row['options'] ?? '{}'), true);

        $row['failed_job_ids'] = \is_array($ids) ? $ids : [];
        $row['options'] = \is_array($options) ? $options : [];

        return $row;
    }
}
