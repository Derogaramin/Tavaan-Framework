<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * DatabaseNewsCategoryRepository — آداپتر دسته‌های خبری (news_categories).
 */

namespace Modules\News\Infrastructure\Persistence;

use Modules\News\Domain\Entities\NewsCategory;
use Modules\News\Domain\Repositories\NewsCategoryRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class DatabaseNewsCategoryRepository implements NewsCategoryRepositoryInterface
{
    public function save(NewsCategory $category): int
    {
        if ($category->id() !== null) {
            DB::table('news_categories')->where('id', $category->id())->update($category->toRow());

            return $category->id();
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        return (int) DB::table('news_categories')->insertGetId([...$category->toRow(), 'created_at' => $now, 'updated_at' => $now]);
    }

    public function findById(int $id): ?NewsCategory
    {
        $row = DB::table('news_categories')->where('id', $id)->first();

        return $row === null ? null : NewsCategory::fromRow($row);
    }

    public function findBySlug(string $slug): ?NewsCategory
    {
        $row = DB::table('news_categories')->where('slug', $slug)->first();

        return $row === null ? null : NewsCategory::fromRow($row);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $query = DB::table('news_categories')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function all(): array
    {
        $rows = DB::table('news_categories')->orderBy('title')->get();

        return \array_map(static fn (array $row): NewsCategory => NewsCategory::fromRow($row), $rows);
    }

    public function withDescendants(int $categoryId): array
    {
        $all = $this->all();

        $childrenOf = [];
        foreach ($all as $category) {
            $parent = $category->parentId();
            if ($parent !== null && $category->id() !== null) {
                $childrenOf[$parent][] = $category->id();
            }
        }

        $out = [$categoryId];
        $queue = [$categoryId];
        $guard = 0;
        while ($queue !== [] && $guard < 1000) {
            $current = \array_shift($queue);
            foreach ($childrenOf[$current] ?? [] as $child) {
                if (!\in_array($child, $out, true)) {
                    $out[] = $child;
                    $queue[] = $child;
                }
            }
            $guard++;
        }

        return $out;
    }

    public function delete(int $id): void
    {
        // فرزندان → ریشه (بدون FK بین‌ماژولی)؛ مقالات → بدون دسته
        DB::table('news_categories')->where('parent_id', $id)->update(['parent_id' => null]);
        DB::table('news_articles')->where('category_id', $id)->update(['category_id' => null]);
        DB::table('news_categories')->where('id', $id)->delete();
    }
}
