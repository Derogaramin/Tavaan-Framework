<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CartWasUpdated — سبد خرید به‌روز شد (DOC-093 §6).
 */

namespace Modules\Shop\Domain\Events;

/**
 * تغییر سبد → notification (یادآوری سبد رهاشده — DOC-093 §6).
 *
 * @api 1.0
 */
final readonly class CartWasUpdated
{
    public function __construct(
        public string $cartId,
        public ?int $userId = null,
        public string $action,
        public int $itemCount,
        public int $subtotalMinor,
    ) {
    }

    public function eventName(): string
    {
        return 'shop.cart.updated';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'cart_id' => $this->cartId,
            'user_id' => $this->userId,
            'action' => $this->action,
            'item_count' => $this->itemCount,
            'subtotal_minor' => $this->subtotalMinor,
        ];
    }
}
