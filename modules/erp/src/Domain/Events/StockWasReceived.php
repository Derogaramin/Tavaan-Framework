<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * StockWasReceived — رسید انبار (DOC-097 §9: inventory.stock.received).
 */

namespace Modules\Erp\Domain\Events;

/**
 * مصرف‌کننده: accounting (سند موجودی/پرداختنی)، procurement (رسید PO).
 *
 * @api 1.0
 */
final readonly class StockWasReceived
{
    /** @param list<array{item_id: string, qty: float, unit_cost: int, kind?: string}> $lines */
    public function __construct(
        public string $movementGroupId,
        public string $warehouseId,
        public array $lines,
        public int $totalCost,
        public string $referenceType,
        public ?string $referenceId,
        public ?string $voucherId = null,
    ) {
    }

    public function eventName(): string
    {
        return 'inventory.stock.received';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'movement_group_id' => $this->movementGroupId,
            'warehouse_id' => $this->warehouseId,
            'lines' => $this->lines,
            'total_cost' => $this->totalCost,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'voucher_id' => $this->voucherId,
        ];
    }
}
