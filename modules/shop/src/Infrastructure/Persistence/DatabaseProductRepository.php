<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * DatabaseProductRepository — آداپتر کاتالوگ (DB Facade → shop_products/variants).
 */

namespace Modules\Shop\Infrastructure\Persistence;

use Modules\Shop\Domain\Entities\Product;
use Modules\Shop\Domain\Entities\ProductVariant;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class DatabaseProductRepository implements ProductRepositoryInterface
{
    public function save(Product $product): void
    {
        $row = $product->toRow();
        $exists = DB::table('shop_products')->where('id', $product->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('shop_products')->where('id', $product->id())->update($row);

            return;
        }

        DB::table('shop_products')->insert($row);
    }

    public function findById(string $id): ?Product
    {
        $row = DB::table('shop_products')->where('id', $id)->first();

        return $row === null ? null : Product::fromRow($row);
    }

    public function findBySlug(string $slug, bool $withTrashed = false): ?Product
    {
        $query = DB::table('shop_products')->where('slug', $slug);
        if (!$withTrashed) {
            $query->whereNull('deleted_at');
        }

        $row = $query->first();

        return $row === null ? null : Product::fromRow($row);
    }

    public function findBySku(string $sku): ?Product
    {
        $row = DB::table('shop_products')->where('sku', $sku)->first();

        return $row === null ? null : Product::fromRow($row);
    }

    public function slugExists(string $slug, ?string $exceptId = null): bool
    {
        $query = DB::table('shop_products')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function skuExists(string $sku, ?string $exceptId = null): bool
    {
        if ($sku === '') {
            return false;
        }
        $query = DB::table('shop_products')->where('sku', $sku);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $base = fn () => $this->filtered($filters);

        $total = $base()->count();

        [$sortCol, $sortDir] = $this->sortOf((string) ($filters['sort'] ?? ''));
        $rows = $base()
            ->orderBy($sortCol, $sortDir)
            ->orderByDesc('id')          // tie-break قطعی
            ->forPage($page, $perPage)
            ->get();

        return [
            'rows' => \array_map(static fn (array $row): Product => Product::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    public function latestActive(int $limit): array
    {
        $rows = DB::table('shop_products')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(static fn (array $row): Product => Product::fromRow($row), $rows);
    }

    public function related(Product $product, int $limit = 4): array
    {
        $rows = DB::table('shop_products')
            ->where('category_id', $product->categoryId())
            ->where('id', '!=', $product->id())
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderByDesc('sold_count')
            ->orderByDesc('id')
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(static fn (array $row): Product => Product::fromRow($row), $rows);
    }

    public function saveVariant(ProductVariant $variant): void
    {
        $row = $variant->toRow();
        $exists = DB::table('shop_product_variants')->where('id', $variant->id())->exists();

        if ($exists) {
            unset($row['id']);
            DB::table('shop_product_variants')->where('id', $variant->id())->update($row);

            return;
        }

        DB::table('shop_product_variants')->insert($row);
    }

    public function findVariant(string $variantId): ?ProductVariant
    {
        $row = DB::table('shop_product_variants')->where('id', $variantId)->first();

        return $row === null ? null : ProductVariant::fromRow($row);
    }

    public function variantsOf(string $productId): array
    {
        $rows = DB::table('shop_product_variants')->where('product_id', $productId)->orderBy('title')->get();

        return \array_map(static fn (array $row): ProductVariant => ProductVariant::fromRow($row), $rows);
    }

    public function deleteVariant(string $variantId): void
    {
        DB::table('shop_product_variants')->where('id', $variantId)->delete();
    }

    public function delete(string $productId): void
    {
        DB::table('shop_product_variants')->where('product_id', $productId)->delete();
        DB::table('shop_products')->where('id', $productId)->delete();
    }

    /** @param array<string, mixed> $filters */
    private function filtered(array $filters): mixed
    {
        $query = DB::table('shop_products');

        if (!(bool) ($filters['with_trashed'] ?? false)) {
            $query->whereNull('deleted_at');
        }
        if (isset($filters['status']) && \is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['category_ids']) && \is_array($filters['category_ids']) && $filters['category_ids'] !== []) {
            $query->whereIn('category_id', \array_map('intval', $filters['category_ids']));
        }
        if (isset($filters['featured']) && $filters['featured'] !== null) {
            $query->where('is_featured', (bool) $filters['featured']);
        }
        if (isset($filters['min_price']) && $filters['min_price'] !== null) {
            $query->where('price', '>=', (int) $filters['min_price']);
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== null) {
            $query->where('price', '<=', (int) $filters['max_price']);
        }
        if (isset($filters['search']) && \is_string($filters['search']) && \trim($filters['search']) !== '') {
            $term = \trim($filters['search']);
            $query->where(static function ($q) use ($term): void {
                $q->where('title', 'like', '%' . $term . '%')
                    ->orWhere('summary', 'like', '%' . $term . '%')
                    ->orWhere('sku', 'like', '%' . $term . '%');
            });
        }

        return $query;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sortOf(string $sort): array
    {
        return match ($sort) {
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            'newest' => ['created_at', 'desc'],
            'popular' => ['sold_count', 'desc'],
            'views' => ['view_count', 'desc'],
            default => ['created_at', 'desc'],
        };
    }
}
