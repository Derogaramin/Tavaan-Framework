<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ProductReaderAdapter — پیاده‌سازی قرارداد عمومی ProductReaderInterface.
 */

namespace Modules\Shop\Infrastructure;

use Modules\Shop\Contracts\ProductReaderInterface;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;

/**
 * @api 1.0
 */
final class ProductReaderAdapter implements ProductReaderInterface
{
    public function __construct(private readonly ProductRepositoryInterface $products)
    {
    }

    public function snapshotById(string $productId): ?array
    {
        $product = $this->products->findById($productId);
        if ($product === null) {
            return null;
        }

        return [
            'id' => $product->id(),
            'title' => $product->title(),
            'slug' => $product->slug(),
            'price_minor' => $product->effectivePrice()->minor,
            'currency' => $product->currency(),
            'stock' => $product->stock(),
            'status' => $product->status()->value,
        ];
    }

    public function priceOf(string $productId): int
    {
        return $this->products->findById($productId)?->effectivePrice()->minor ?? 0;
    }

    public function titleOf(string $productId, string $fallback = 'محصول'): string
    {
        return $this->products->findById($productId)?->title() ?? $fallback;
    }
}
