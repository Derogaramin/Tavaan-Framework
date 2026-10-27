<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqItemRepositoryInterface — قرارداد مخزن پرسش‌ها.
 */

namespace Modules\Faq\Domain\Repositories;

use Modules\Faq\Domain\Entities\FaqItem;

/**
 * مخزن پرسش‌های متداول (نگاشت در Infrastructure — وارونگی وابستگی).
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
interface FaqItemRepositoryInterface
{
    /** insert-or-update بر اساس وجود id. */
    public function save(FaqItem $item): void;

    public function findById(string $id): ?FaqItem;

    /** @return list<FaqItem> همهٔ پرسش‌های منتشرشدهٔ زنده (sort_order asc سپس created_at asc) */
    public function publishedAll(): array;

    /**
     * صفحه‌بندی با فیلتر.
     *
     * @param array<string, mixed> $filters کلیدها: status, category_id, q (LIKE روی question), with_trashed
     *
     * @return array{rows: list<FaqItem>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    public function delete(string $id): void;

    /** افزایش شمارندهٔ رأی «مفید بود» (بدون read-modify-write). */
    public function incrementHelpful(string $id, bool $yes): void;

    public function countPublished(): int;

    public function countByCategory(string $categoryId): int;
}
