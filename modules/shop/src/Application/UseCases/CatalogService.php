<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CatalogService — خواندن کاتالوگ با کش (DOC-093 §9).
 */

namespace Modules\Shop\Application\UseCases;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Shop\Domain\Entities\Product;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Modules\Shop\Domain\Repositories\ShopCategoryRepositoryInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Search;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class CatalogService
{
    /** @param array<string, mixed> $config کلید config('shop') */
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ShopCategoryRepositoryInterface $categories,
        private readonly UserReaderInterface $users,
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{rows: list<Product>, total: int, page: int, perPage: int}
     */
    public function listActive(array $filters = [], int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?? (int) ($this->config['per_page'] ?? 12);
        $page = \max(1, $page);
        $filters['status'] = 'active';

        $key = 'catalog:' . \substr(\hash('xxh128', (string) \json_encode($filters) . '|' . $page . '|' . $perPage), 0, 16);
        $result = $this->remember($key, (int) ($this->config['cache']['list_ttl'] ?? 60), function () use ($filters, $page, $perPage): array {
            $data = $this->products->paginate($filters, $page, $perPage);
            $data['rows'] = \array_map(static fn (Product $p): array => $p->toRow(), $data['rows']);

            return $data;
        });

        $rows = \array_map(static fn (array $row): Product => Product::fromRow($row), (array) ($result['rows'] ?? []));

        return ['rows' => $rows, 'total' => (int) ($result['total'] ?? 0), 'page' => $page, 'perPage' => $perPage];
    }

    /** نمایش محصول فعال + شمارش بازدید. */
    public function showActive(string $slug, bool $countView = true): Product
    {
        $product = $this->products->findBySlug($slug);
        if ($product === null || !$product->status()->isPublic() || $product->isTrashed()) {
            throw RouteNotFoundException::forPath('GET', '/shop/products/' . $slug);
        }

        if ($countView) {
            $product->incrementViews();
            $this->products->save($product);
            $product->pullEvents();
        }

        return $product;
    }

    /** جست‌وجوی فارسی (ایندکس + fallback). */
    public function search(string $query, int $page = 1, ?int $perPage = null): array
    {
        $query = \trim($query);
        if ($query === '') {
            return $this->listActive([], $page, $perPage);
        }

        $index = (string) ($this->config['search']['index'] ?? 'shop_products');
        $ids = [];
        if ((bool) ($this->config['search']['enabled'] ?? true)) {
            try {
                $searchResult = Search::search($index, $query, ['limit' => 100]);
                foreach ((array) (\method_exists($searchResult, 'hits') ? $searchResult->hits() : []) as $hit) {
                    $id = \is_array($hit) ? ($hit['id'] ?? null) : null;
                    if (\is_string($id) && $id !== '') {
                        $ids[] = $id;
                    }
                }
            } catch (\Throwable) {
                $ids = [];
            }
        }

        if ($ids !== []) {
            $rows = [];
            foreach ($ids as $id) {
                $product = $this->products->findById($id);
                if ($product !== null && $product->status()->isPublic() && !$product->isTrashed()) {
                    $rows[] = $product;
                }
            }

            return ['rows' => $rows, 'total' => \count($rows), 'page' => 1, 'perPage' => $perPage ?? (int) ($this->config['per_page'] ?? 12)];
        }

        return $this->listActive(['search' => $query], $page, $perPage);
    }

    /** @return list<Product> */
    public function related(Product $product, int $limit = 4): array
    {
        return $this->products->related($product, $limit);
    }

    public function authorName(int $userId): string
    {
        return $this->users->displayNameOf($userId, 'فروشگاه');
    }

    /** invalidate کش کاتالوگ (توسط نوشتن‌ها). */
    public function invalidateCache(): void
    {
        if (!(bool) ($this->config['cache']['enabled'] ?? true)) {
            return;
        }

        try {
            Cache::tags(['shop'])->flush();
        } catch (\Throwable) {
            // درایور بدون tag
        }
    }

    /** @return mixed */
    private function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (!(bool) ($this->config['cache']['enabled'] ?? true) || $ttl <= 0) {
            return $callback();
        }

        try {
            return Cache::tags(['shop'])->remember('shop:' . $key, $ttl, $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }
}
