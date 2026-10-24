<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ShopCategoryRepositoryInterface — پورت دسته‌های فروشگاه.
 */

namespace Modules\Shop\Domain\Repositories;

use Modules\Shop\Domain\Entities\ShopCategory;

/**
 * @api 1.0
 */
interface ShopCategoryRepositoryInterface
{
    public function save(ShopCategory $category): int;

    public function findById(int $id): ?ShopCategory;

    public function findBySlug(string $slug): ?ShopCategory;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    /** @return list<ShopCategory> */
    public function all(): array;

    /** شناسه + همهٔ نوادگان. @return list<int> */
    public function withDescendants(int $categoryId): array;

    public function delete(int $id): void;
}
