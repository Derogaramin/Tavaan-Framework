<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * DatabaseShopCategoryRepository — آداپتر دسته‌های فروشگاه.
 */

namespace Modules\Shop\Infrastructure\Persistence;

use Modules\Shop\Domain\Entities\ShopCategory;
use Modules\Shop\Domain\Repositories\ShopCategoryRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 */
final class DatabaseShopCategoryRepository implements ShopCategoryRepositoryInterface
{
    public function save(ShopCategory $category): int
    {
        if ($category->id() !== null) {
            DB::table('shop_categories')->where('id', $category->id())->update($category->toRow());

            return $category->id();
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        return (int) DB::table('shop_categories')->insertGetId([...$category->toRow(), 'created_at' => $now, 'updated_at' => $now]);
    }

    public function findById(int $id): ?ShopCategory
    {
        $row = DB::table('shop_categories')->where('id', $id)->first();

        return $row === null ? null : ShopCategory::fromRow($row);
    }

    public function findBySlug(string $slug): ?ShopCategory
    {
        $row = DB::table('shop_categories')->where('slug', $slug)->first();

        return $row === null ? null : ShopCategory::fromRow($row);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $query = DB::table('shop_categories')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function all(): array
    {
        $rows = DB::table('shop_categories')->orderBy('title')->get();

        return \array_map(static fn (array $row): ShopCategory => ShopCategory::fromRow($row), $rows);
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
        DB::table('shop_categories')->where('parent_id', $id)->update(['parent_id' => null]);
        DB::table('shop_categories')->where('id', $id)->delete();
    }
}
