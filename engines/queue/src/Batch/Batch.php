<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Batch;

/**
 * تَوان | Tavaan — Queue
 * Batch — نمای وضعیت یک batch ثبت‌شده (DOC-061 §3).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class Batch
{
    /**
     * @param array<string, mixed> $record رکورد مخزن (id, name, total_jobs, …)
     */
    public function __construct(private readonly array $record)
    {
    }

    public function id(): string
    {
        return (string) ($this->record['id'] ?? '');
    }

    public function name(): string
    {
        return (string) ($this->record['name'] ?? '');
    }

    public function totalJobs(): int
    {
        return (int) ($this->record['total_jobs'] ?? 0);
    }

    public function pendingJobs(): int
    {
        return (int) ($this->record['pending_jobs'] ?? 0);
    }

    public function failedJobs(): int
    {
        return (int) ($this->record['failed_jobs'] ?? 0);
    }

    /** @return list<string> */
    public function failedJobIds(): array
    {
        $ids = $this->record['failed_job_ids'] ?? [];

        return \is_array($ids) ? \array_values(\array_map(strval(...), $ids)) : [];
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        return \is_array($this->record['options'] ?? null) ? $this->record['options'] : [];
    }

    public function cancelled(): bool
    {
        return ($this->record['cancelled_at'] ?? null) !== null;
    }

    public function finished(): bool
    {
        return ($this->record['finished_at'] ?? null) !== null;
    }

    public function allowsFailures(): bool
    {
        return (bool) ($this->options()['allowFailures'] ?? false);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->record;
    }
}
