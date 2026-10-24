<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * CategoryRepositoryInterface — پورت ماندگاری دسته.
 */

namespace Modules\Blog\Domain\Repositories;

use Modules\Blog\Domain\Entities\Category;

/**
 * @api 1.0
 */
interface CategoryRepositoryInterface
{
    public function save(Category $category): int;

    public function findById(int $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    /** @return list<Category> */
    public function all(): array;

    public function delete(int $id): void;
}
