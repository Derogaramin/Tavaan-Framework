<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqCategoryRepositoryInterface — قرارداد مخزن دسته‌ها.
 */

namespace Modules\Faq\Domain\Repositories;

use Modules\Faq\Domain\Entities\FaqCategory;

/**
 * مخزن دسته‌های پرسش (نگاشت در Infrastructure — وارونگی وابستگی).
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
interface FaqCategoryRepositoryInterface
{
    /** insert-or-update بر اساس وجود id. */
    public function save(FaqCategory $category): void;

    public function findById(string $id): ?FaqCategory;

    public function findBySlug(string $slug): ?FaqCategory;

    public function slugExists(string $slug, ?string $exceptId = null): bool;

    /** @return list<FaqCategory> همهٔ دسته‌های زنده به ترتیب sort_order سپس عنوان */
    public function all(): array;

    /** شمار دسته‌های زنده (بدون trash). */
    public function countAll(): int;
}
