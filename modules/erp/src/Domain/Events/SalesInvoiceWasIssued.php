<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * SalesInvoiceWasIssued — فاکتور فروش صادر شد (DOC-097 §9: sales.invoice.issued).
 */

namespace Modules\Erp\Domain\Events;

/**
 * مصرف‌کننده: accounting (درآمد + حساب دریافتنی)، inventory (کاهش/COGS).
 *
 * @api 1.0
 */
final readonly class SalesInvoiceWasIssued
{
    public function __construct(
        public string $invoiceId,
        public string $invoiceNumber,
        public string $customerId,
        public int $amountMinor,
        public int $cogsMinor,
        public ?string $salesOrderId,
        public ?string $voucherId,
    ) {
    }

    public function eventName(): string
    {
        return 'sales.invoice.issued';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'customer_id' => $this->customerId,
            'amount_minor' => $this->amountMinor,
            'cogs_minor' => $this->cogsMinor,
            'sales_order_id' => $this->salesOrderId,
            'voucher_id' => $this->voucherId,
        ];
    }
}
