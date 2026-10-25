<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * WorkOrderWasCompleted — سفارش تولید تکمیل شد (DOC-097 §9: production.order.completed).
 */

namespace Modules\Erp\Domain\Events;

/**
 * مصرف‌کننده: inventory (رسید محصول)، accounting (بهای تمام‌شده).
 *
 * @api 1.0
 */
final readonly class WorkOrderWasCompleted
{
    public function __construct(
        public string $workOrderId,
        public string $woNumber,
        public string $productId,
        public float $producedQty,
        public int $materialCost,
        public int $unitCost,
        public ?string $voucherId,
    ) {
    }

    public function eventName(): string
    {
        return 'production.order.completed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'work_order_id' => $this->workOrderId,
            'wo_number' => $this->woNumber,
            'product_id' => $this->productId,
            'produced_qty' => $this->producedQty,
            'material_cost' => $this->materialCost,
            'unit_cost' => $this->unitCost,
            'voucher_id' => $this->voucherId,
        ];
    }
}
