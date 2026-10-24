<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderWasCanceled — سفارش لغو شد (DOC-093 §6).
 */

namespace Modules\Shop\Domain\Events;

/**
 * لغو → inventory (release موجودی) + refund اگر پرداخت‌شده بود (DOC-093 §6).
 *
 * @api 1.0
 */
final readonly class OrderWasCanceled
{
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public int $customerId,
        public bool $wasPaid,
        public int $totalMinor,
        public string $currency,
        public string $reason,
    ) {
    }

    public function eventName(): string
    {
        return 'shop.order.canceled';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'customer_id' => $this->customerId,
            'was_paid' => $this->wasPaid,
            'total_minor' => $this->totalMinor,
            'currency' => $this->currency,
            'reason' => $this->reason,
        ];
    }
}
