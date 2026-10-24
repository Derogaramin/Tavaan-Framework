<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ProductWasUpdated — محصول به‌روز شد (DOC-093 §6).
 */

namespace Modules\Shop\Domain\Events;

/**
 * پس‌لرزهٔ ویرایش/تغییر وضعیت محصول → search (reindex) + cache (invalidate).
 *
 * @api 1.0
 */
final readonly class ProductWasUpdated
{
    public function __construct(
        public string $productId,
        public string $slug,
        public string $title,
        public \Modules\Shop\Domain\Enums\ProductStatus $status,
        public int $priceMinor,
        public string $currency,
    ) {
    }

    public function eventName(): string
    {
        return 'shop.product.updated';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'product_id' => $this->productId,
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status->value,
            'price_minor' => $this->priceMinor,
            'currency' => $this->currency,
        ];
    }
}
