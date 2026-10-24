<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * DatabaseCategoryRepository — آداپتر ماندگاری دسته (blog_categories).
 */

namespace Modules\Blog\Infrastructure\Persistence;

use Modules\Blog\Domain\Entities\Category;
use Modules\Blog\Domain\Repositories\CategoryRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/blog/README.md
 */
final class DatabaseCategoryRepository implements CategoryRepositoryInterface
{
    public function save(Category $category): int
    {
        if ($category->id() !== null) {
            DB::table('blog_categories')->where('id', $category->id())->update($category->toRow());

            return $category->id();
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        return (int) DB::table('blog_categories')->insertGetId([...$category->toRow(), 'created_at' => $now, 'updated_at' => $now]);
    }

    public function findById(int $id): ?Category
    {
        $row = DB::table('blog_categories')->where('id', $id)->first();

        return $row === null ? null : Category::fromRow($row);
    }

    public function findBySlug(string $slug): ?Category
    {
        $row = DB::table('blog_categories')->where('slug', $slug)->first();

        return $row === null ? null : Category::fromRow($row);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $query = DB::table('blog_categories')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function all(): array
    {
        $rows = DB::table('blog_categories')->orderBy('name')->get();

        return \array_map(static fn (array $row): Category => Category::fromRow($row), $rows);
    }

    public function delete(int $id): void
    {
        // قطع وابستگی مقالات (set null) — بدون FK بین‌ماژولی
        DB::table('blog_posts')->where('category_id', $id)->update(['category_id' => null]);
        DB::table('blog_categories')->where('id', $id)->delete();
    }
}
