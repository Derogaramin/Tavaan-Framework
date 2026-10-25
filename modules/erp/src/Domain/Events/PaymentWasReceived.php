<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PaymentWasReceived — دریافت از مشتری (sales.payment.received).
 */

namespace Modules\Erp\Domain\Events;

/**
 * @api 1.0
 */
final readonly class PaymentWasReceived
{
    public function __construct(
        public string $paymentId,
        public string $customerId,
        public int $amountMinor,
        public ?string $salesInvoiceId,
        public string $method,
        public ?string $voucherId,
    ) {
    }

    public function eventName(): string
    {
        return 'sales.payment.received';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'customer_id' => $this->customerId,
            'amount_minor' => $this->amountMinor,
            'sales_invoice_id' => $this->salesInvoiceId,
            'method' => $this->method,
            'voucher_id' => $this->voucherId,
        ];
    }
}
