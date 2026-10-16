<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Batch;

use Tavaan\Core\Contracts\Queue\BatchRepositoryInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Queue
 * ArrayBatchRepository — مخزن batch در حافظه (تست/dev؛ DOC-061 §3).
 *
 * تنها مخزنی که Closure در finally را نگه می‌دارد (غیرماندگار).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class ArrayBatchRepository implements BatchRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $batches = [];

    /** @var array<string, \Closure> */
    private array $closures = [];

    /** @param array<string, mixed> $options */
    public function create(string $id, int $totalJobs, array $options): void
    {
        $this->batches[$id] = [
            'id' => $id,
            'name' => (string) ($options['name'] ?? ''),
            'total_jobs' => $totalJobs,
            'pending_jobs' => $totalJobs,
            'failed_jobs' => 0,
            'failed_job_ids' => [],
            'options' => $options,
            'cancelled_at' => null,
            'created_at' => Clock::timestamp(),
            'finished_at' => null,
        ];
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->batches[$id] ?? null;
    }

    public function recordSuccess(string $id, string $jobUuid): bool
    {
        if (!isset($this->batches[$id])) {
            return false;
        }

        $this->batches[$id]['pending_jobs'] = \max(0, (int) $this->batches[$id]['pending_jobs'] - 1);

        return (int) $this->batches[$id]['pending_jobs'] === 0;
    }

    public function recordFailure(string $id, string $jobUuid, bool $allowFailures): bool
    {
        if (!isset($this->batches[$id])) {
            return false;
        }

        $batch = &$this->batches[$id];
        $batch['pending_jobs'] = \max(0, (int) $batch['pending_jobs'] - 1);
        $batch['failed_jobs'] = (int) $batch['failed_jobs'] + 1;
        $batch['failed_job_ids'][] = $jobUuid;

        $finished = (int) $batch['pending_jobs'] === 0;
        unset($batch);

        return $finished;
    }

    public function cancel(string $id): void
    {
        if (isset($this->batches[$id]) && $this->batches[$id]['cancelled_at'] === null) {
            $this->batches[$id]['cancelled_at'] = Clock::timestamp();
        }
    }

    public function finish(string $id): void
    {
        if (isset($this->batches[$id])) {
            $this->batches[$id]['finished_at'] = Clock::timestamp();
        }
    }

    public function delete(string $id): void
    {
        unset($this->batches[$id]);
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit = 50): array
    {
        return \array_slice(\array_reverse(\array_values($this->batches)), 0, $limit);
    }

    /** ثبت Closure برای token (فقط تست). */
    public function registerClosure(string $token, \Closure $closure): void
    {
        $this->closures[$token] = $closure;
    }

    public function closure(string $token): ?\Closure
    {
        return $this->closures[$token] ?? null;
    }
}
