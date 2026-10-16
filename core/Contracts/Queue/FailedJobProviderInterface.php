<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Queue;

/**
 * تَوان | Tavaan — Contracts
 * FailedJobProviderInterface — ذخیره‌سازی jobهای شکست‌خورده (DOC-061 §5/§9).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
interface FailedJobProviderInterface
{
    /**
     * ثبت یک job شکست‌خورده.
     *
     * @param string $payload  payload کامل JSON
     * @param int|string|null $id شناسهٔ job در اتصال اصلی (اختیاری)
     *
     * @return string uuid ثبت‌شده
     */
    public function log(string $connection, string $queue, string $payload, \Throwable $e, int|string|null $id = null): string;

    /**
     * همهٔ رکوردهای شکست (جدیدترین ابتدا).
     *
     * @return list<array<string, mixed>>
     */
    public function all(int $limit = 100): array;

    /** @return array<string, mixed>|null */
    public function find(int|string $idOrUuid): ?array;

    /** حذف یک رکورد شکست (برای queue:retry). */
    public function forget(int|string $idOrUuid): bool;

    /**
     * حذف همهٔ رکوردها (یا قدیمی‌تر از $hours ساعت).
     *
     * @return int تعداد حذف‌شده
     */
    public function flush(?int $hours = null): int;

    /** تعداد رکوردهای شکست. */
    public function count(): int;
}
