<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderWasPaid — سفارش پرداخت شد (DOC-093 §4.3/§6).
 */

namespace Modules\Shop\Domain\Events;

/**
 * پرداخت موفق → mail/sms فاکتور، inventory (sale قطعی)، crm/loyalty، report (DOC-093 §4.3).
 *
 * @api 1.0
 */
final readonly class OrderWasPaid
{
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public int $customerId,
        public int $totalMinor,
        public string $currency,
        public string $authority,
    ) {
    }

    public function eventName(): string
    {
        return 'shop.order.paid';
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
            'authority' => $this->authority,
        ];
    }
}
