<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ProductSearchIndexer — ایندکس جست‌وجوی فارسی محصول (DOC-093 §8 + AC-SHOP-04).
 */

namespace Modules\Shop\Infrastructure\Search;

use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Tavaan\Core\Facade\Facades\Search;

/**
 * شنوندهٔ `shop.product.updated` — best-effort (جریان اصلی نمی‌شکند).
 *
 * @api 1.0
 */
final class ProductSearchIndexer
{
    /** @param array<string, mixed> $config کلید config('shop.search') */
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly array $config = [],
    ) {
    }

    /** قرارداد listener رویداد رشته‌ای: ($eventName, $payload). */
    public function onProductUpdated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $id = (string) ($payload['product_id'] ?? '');
        $status = (string) ($payload['status'] ?? '');

        if ($status === 'active') {
            $this->index($id);

            return;
        }
        $this->remove($id);
    }

    public function onProductDeleted(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->remove((string) ($payload['product_id'] ?? ''));
    }

    private function index(string $productId): void
    {
        if (!$this->enabled() || $productId === '') {
            return;
        }

        try {
            $product = $this->products->findById($productId);
            if ($product === null || !$product->status()->isPublic() || $product->isTrashed()) {
                return;
            }

            Search::index($this->indexName(), [
                'id' => $product->id(),
                'title' => $product->title(),
                'slug' => $product->slug(),
                'summary' => (string) $product->summary(),
                'body' => \strip_tags((string) $product->description()),
                'sku' => (string) $product->sku(),
                'category_id' => $product->categoryId(),
                'price' => $product->effectivePrice()->minor,
                'in_stock' => $product->stock() > 0,
            ], $product->id());
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function remove(string $productId): void
    {
        if (!$this->enabled() || $productId === '') {
            return;
        }

        try {
            Search::delete($this->indexName(), $productId);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    private function indexName(): string
    {
        return (string) ($this->config['index'] ?? 'shop_products');
    }
}
