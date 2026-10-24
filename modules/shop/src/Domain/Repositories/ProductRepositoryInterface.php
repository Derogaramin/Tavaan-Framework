<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ProductRepositoryInterface — پورت ماندگاری محصول/variant.
 */

namespace Modules\Shop\Domain\Repositories;

use Modules\Shop\Domain\Entities\Product;
use Modules\Shop\Domain\Entities\ProductVariant;

/**
 * @api 1.0
 */
interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(string $id): ?Product;

    public function findBySlug(string $slug, bool $withTrashed = false): ?Product;

    public function findBySku(string $sku): ?Product;

    public function slugExists(string $slug, ?string $exceptId = null): bool;

    public function skuExists(string $sku, ?string $exceptId = null): bool;

    /**
     * @param array{
     *     status?: string|null,
     *     category_ids?: list<int>|null,
     *     featured?: bool|null,
     *     search?: string|null,
     *     min_price?: int|null,
     *     max_price?: int|null,
     *     sort?: string|null,
     *     with_trashed?: bool,
     * } $filters
     *
     * @return array{rows: list<Product>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /** @return list<Product> */
    public function latestActive(int $limit): array;

    /** محصولات مرتبط (هم‌دسته، بدون خود محصول). @return list<Product> */
    public function related(Product $product, int $limit = 4): array;

    // ─── variantها ────────────────────────────────────────────────────────

    public function saveVariant(ProductVariant $variant): void;

    public function findVariant(string $variantId): ?ProductVariant;

    /** @return list<ProductVariant> */
    public function variantsOf(string $productId): array;

    public function deleteVariant(string $variantId): void;

    public function delete(string $productId): void;
}
