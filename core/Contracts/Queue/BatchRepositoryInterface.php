<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Queue;

/**
 * تَوان | Tavaan — Contracts
 * BatchRepositoryInterface — ذخیره‌سازی وضعیت Batchها (DOC-061 §5: tvn_job_batches).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
interface BatchRepositoryInterface
{
    /**
     * ساخت رکورد batch.
     *
     * @param array<string, mixed> $options (name, allowFailures, finally, queue, connection)
     */
    public function create(string $id, int $totalJobs, array $options): void;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /**
     * ثبت موفقیت یک job — شمارش pending را کم می‌کند.
     *
     * @return bool آیا batch تمام‌شده (pending=0) است؟
     */
    public function recordSuccess(string $id, string $jobUuid): bool;

    /**
     * ثبت شکست نهایی یک job.
     *
     * @return bool آیا batch تمام‌شده است؟
     */
    public function recordFailure(string $id, string $jobUuid, bool $allowFailures): bool;

    /** علامت‌گذاری batch به‌عنوان لغوشده. */
    public function cancel(string $id): void;

    /** علامت‌گذاری پایان (finished_at). */
    public function finish(string $id): void;

    public function delete(string $id): void;

    /**
     * همهٔ batchها (جدیدترین ابتدا).
     *
     * @return list<array<string, mixed>>
     */
    public function all(int $limit = 50): array;
}
