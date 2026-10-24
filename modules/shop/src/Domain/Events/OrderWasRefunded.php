<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderWasRefunded — سفارش مسترد شد (DOC-093 §5).
 */

namespace Modules\Shop\Domain\Events;

/**
 * استرداد وجه → payment (refund) + report.
 *
 * @api 1.0
 */
final readonly class OrderWasRefunded
{
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public int $customerId,
        public int $totalMinor,
        public string $currency,
        public string $reason,
    ) {
    }

    public function eventName(): string
    {
        return 'shop.order.refunded';
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
            'reason' => $this->reason,
        ];
    }
}
