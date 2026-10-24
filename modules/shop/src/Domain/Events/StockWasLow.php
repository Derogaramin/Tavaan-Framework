<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * StockWasLow — موجودی محصول کم شد (DOC-093 §6).
 */

namespace Modules\Shop\Domain\Events;

/**
 * موجودی کم → notification به مدیر (DOC-093 §6).
 *
 * @api 1.0
 */
final readonly class StockWasLow
{
    public function __construct(
        public string $productId,
        public string $title,
        public int $stock,
        public int $threshold,
    ) {
    }

    public function eventName(): string
    {
        return 'shop.stock.low';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'product_id' => $this->productId,
            'title' => $this->title,
            'stock' => $this->stock,
            'threshold' => $this->threshold,
        ];
    }
}
