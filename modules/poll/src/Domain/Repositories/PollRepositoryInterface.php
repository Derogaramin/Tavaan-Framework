<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollRepositoryInterface — پورت ماندگاری نظرسنجی‌ها (Hexagonal).
 */

namespace Modules\Poll\Domain\Repositories;

use Modules\Poll\Domain\Entities\Poll;

/**
 * @api 1.0
 * @see modules/poll/README.md
 */
interface PollRepositoryInterface
{
    /** درج یا به‌روزرسانی (id کلید است). */
    public function save(Poll $poll): void;

    public function findById(string $id): ?Poll;

    public function findBySlug(string $slug, bool $withTrashed = false): ?Poll;

    public function slugExists(string $slug, ?string $exceptId = null): bool;

    /**
     * صفحه‌بندی با فیلتر (پنل مدیریت).
     *
     * فیلترهای پشتیبانی‌شده: `status`، `author_id`، `with_trashed`.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{rows: list<Poll>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /**
     * نظرسنجی‌های باز (و در پنجرهٔ زمانی) برای فهرست عمومی — ends_at صعودی.
     *
     * @return list<Poll>
     */
    public function listOpen(int $limit): array;

    public function delete(string $id): void;

    public function countOpen(): int;

    public function countAll(): int;
}
