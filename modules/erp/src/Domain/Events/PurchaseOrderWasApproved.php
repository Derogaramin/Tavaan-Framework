<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PurchaseOrderWasApproved — سفارش خرید تأیید شد (DOC-097 §9: procurement.po.approved).
 */

namespace Modules\Erp\Domain\Events;

/**
 * @api 1.0
 */
final readonly class PurchaseOrderWasApproved
{
    public function __construct(
        public string $poId,
        public string $poNumber,
        public string $supplierId,
        public int $totalMinor,
        public string $currency,
        public ?int $financeApprovedBy,
        public ?int $ceoApprovedBy,
        public ?string $workflowInstanceId = null,
    ) {
    }

    public function eventName(): string
    {
        return 'procurement.po.approved';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'po_id' => $this->poId,
            'po_number' => $this->poNumber,
            'supplier_id' => $this->supplierId,
            'total_minor' => $this->totalMinor,
            'currency' => $this->currency,
            'finance_approved_by' => $this->financeApprovedBy,
            'ceo_approved_by' => $this->ceoApprovedBy,
            'workflow_instance_id' => $this->workflowInstanceId,
        ];
    }
}
