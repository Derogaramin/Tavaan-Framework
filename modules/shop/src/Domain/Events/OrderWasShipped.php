<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderWasShipped — سفارش ارسال شد (DOC-093 §6).
 */

namespace Modules\Shop\Domain\Events;

/**
 * ارسال → notification/sms با کد رهگیری.
 *
 * @api 1.0
 */
final readonly class OrderWasShipped
{
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public int $customerId,
        public string $trackingCode,
    ) {
    }

    public function eventName(): string
    {
        return 'shop.order.shipped';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'customer_id' => $this->customerId,
            'tracking_code' => $this->trackingCode,
        ];
    }
}
