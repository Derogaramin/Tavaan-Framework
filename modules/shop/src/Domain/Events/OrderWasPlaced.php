<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderWasPlaced — سفارش ثبت شد (DOC-093 §4.2/§6).
 */

namespace Modules\Shop\Domain\Events;

/**
 * ثبت سفارش → inventory (reserve در تراکنش انجام شد) + notification.
 *
 * @api 1.0
 */
final readonly class OrderWasPlaced
{
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public int $customerId,
        public int $totalMinor,
        public string $currency,
    ) {
    }

    public function eventName(): string
    {
        return 'shop.order.placed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'customer_id' => $this->customerId,
            'total_minor' => $this->totalMinor,
            'currency' => $this->currency,
        ];
    }
}
