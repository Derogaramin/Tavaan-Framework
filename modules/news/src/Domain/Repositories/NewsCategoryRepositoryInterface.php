<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * NewsCategoryRepositoryInterface — پورت ماندگاری دسته‌های خبری (تو‌در‌تو).
 */

namespace Modules\News\Domain\Repositories;

use Modules\News\Domain\Entities\NewsCategory;

/**
 * @api 1.0
 */
interface NewsCategoryRepositoryInterface
{
    public function save(NewsCategory $category): int;

    public function findById(int $id): ?NewsCategory;

    public function findBySlug(string $slug): ?NewsCategory;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    /** @return list<NewsCategory> همهٔ دسته‌ها (برای ساخت درخت) */
    public function all(): array;

    /**
     * شناسهٔ دسته + همهٔ نوادگان (برای فیلتر زیردسته‌ها).
     *
     * @return list<int>
     */
    public function withDescendants(int $categoryId): array;

    public function delete(int $id): void;
}
