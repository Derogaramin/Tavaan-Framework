<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * GoodsReceiptWasRegistered — رسید کالا ثبت شد.
 */

namespace Modules\Erp\Domain\Events;

/**
 * @api 1.0
 */
final readonly class GoodsReceiptWasRegistered
{
    public function __construct(
        public string $receiptId,
        public string $receiptNumber,
        public ?string $poId,
        public string $warehouseId,
        public int $totalCost,
        public ?string $voucherId,
    ) {
    }

    public function eventName(): string
    {
        return 'procurement.receipt.registered';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'receipt_id' => $this->receiptId,
            'receipt_number' => $this->receiptNumber,
            'po_id' => $this->poId,
            'warehouse_id' => $this->warehouseId,
            'total_cost' => $this->totalCost,
            'voucher_id' => $this->voucherId,
        ];
    }
}
