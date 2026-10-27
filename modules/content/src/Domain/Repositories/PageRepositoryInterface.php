<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * PageRepositoryInterface — پورت ماندگاری صفحات CMS (Hexagonal).
 */

namespace Modules\Content\Domain\Repositories;

use Modules\Content\Domain\Entities\Page;

/**
 * @api 1.0
 * @see modules/content/README.md
 */
interface PageRepositoryInterface
{
    /** درج یا به‌روزرسانی (id کلید است). */
    public function save(Page $page): void;

    public function findById(string $id): ?Page;

    public function findBySlug(string $slug, bool $withTrashed = false): ?Page;

    public function slugExists(string $slug, ?string $exceptId = null): bool;

    /**
     * صفحه‌بندی با فیلتر (پنل مدیریت).
     *
     * فیلترهای پشتیبانی‌شده: `status`، `author_id`، `with_trashed`.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{rows: list<Page>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /**
     * صفحات منتشرشده برای فهرست عمومی (به‌ترتیب انتشار نزولی).
     *
     * @return list<Page>
     */
    public function latestPublished(int $limit): array;

    public function incrementViews(string $id, int $by = 1): void;

    public function delete(string $id): void;

    public function countPublished(): int;
}
