<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PurchaseOrder — سفارش خرید با تأیید سلسله‌مراتبی (DOC-097 §2.3/§3).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Enums\PurchaseOrderStatus;
use Modules\Erp\Domain\Events\PurchaseOrderWasApproved;

/**
 * PurchaseOrder — چرخهٔ تأیید بر اساس مبلغ (E2E-06):
 *
 * ```
 * ≤ finance_threshold → approved (خودکار)
 * > finance_threshold → pending_finance → pending_ceo (> ceo_threshold) → approved
 * ```
 *
 * مسیر توسط ApprovalRouter (دامنهٔ خالص) تصمیم گرفته می‌شود؛ Workflow
 * `erp-purchase-approval` تاریخچه/visualization می‌دهد.
 *
 * @api 1.0
 */
final class PurchaseOrder
{
    /** @var list<object> */
    private array $recordedEvents = [];

    /** @var list<PurchaseOrderItem> */
    private array $items;

    private function __construct(
        private readonly string $id,
        private readonly string $poNumber,
        private readonly string $supplierId,
        private PurchaseOrderStatus $status,
        private int $total,
        private readonly string $currency,
        private ?string $workflowInstanceId,
        private ?int $createdBy,
        private ?int $financeApprovedBy,
        private ?int $ceoApprovedBy,
        private ?\DateTimeImmutable $approvedAt,
        private ?string $rejectReason,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        array $items = [],
    ) {
        $this->items = $items;
    }

    /** @param list<PurchaseOrderItem> $items */
    public static function create(string $poNumber, string $supplierId, array $items, ?int $createdBy = null, string $currency = 'IRR', ?\DateTimeImmutable $at = null): self
    {
        if ($items === []) {
            throw new \DomainException('سفارش خرید بدون قلم ساخته نمی‌شود.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $selfId = \Tavaan\Support\Ulid::generate($now)->toString();

        // 🔒 اقلام به شناسهٔ واقعی سفارش بازشانده می‌شوند
        $items = \array_map(static fn (PurchaseOrderItem $item): PurchaseOrderItem => $item->withParent($selfId), $items);

        $total = 0;
        foreach ($items as $item) {
            $total += $item->lineTotal();
        }

        return new self(
            $selfId,
            $poNumber,
            $supplierId,
            PurchaseOrderStatus::Draft,
            $total,
            $currency,
            null,
            $createdBy,
            null,
            null,
            null,
            null,
            $now,
            null,
            $items,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<PurchaseOrderItem> $items
     */
    public static function fromRow(array $row, array $items = []): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['po_number'] ?? ''),
            (string) ($row['supplier_id'] ?? ''),
            PurchaseOrderStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? PurchaseOrderStatus::Draft,
            (int) ($row['total'] ?? 0),
            (string) ($row['currency'] ?? 'IRR'),
            isset($row['workflow_instance_id']) && $row['workflow_instance_id'] !== null && $row['workflow_instance_id'] !== '' ? (string) $row['workflow_instance_id'] : null,
            isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            isset($row['finance_approved_by']) && $row['finance_approved_by'] !== null ? (int) $row['finance_approved_by'] : null,
            isset($row['ceo_approved_by']) && $row['ceo_approved_by'] !== null ? (int) $row['ceo_approved_by'] : null,
            self::date($row['approved_at'] ?? null),
            isset($row['reject_reason']) && $row['reject_reason'] !== null ? (string) $row['reject_reason'] : null,
            self::date($row['created_at'] ?? null),
            self::date($row['updated_at'] ?? null),
            $items,
        );
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    public function attachWorkflow(string $instanceId): void
    {
        $this->workflowInstanceId ??= $instanceId !== '' ? $instanceId : null;
    }

    /** ثبت برای تأیید — مقصد از ApprovalRouter (pending_finance|pending_ceo|approved). */
    public function submitForApproval(PurchaseOrderStatus $target): void
    {
        if (!\in_array($target, [PurchaseOrderStatus::PendingFinance, PurchaseOrderStatus::PendingCeo, PurchaseOrderStatus::Approved], true)) {
            throw new \DomainException('مقصد ثبت برای تأیید نامعتبر است.');
        }

        $this->transitionTo($target);
        if ($target === PurchaseOrderStatus::Approved) {
            $this->finalizeApproval(null);
        }
        $this->touch();
    }

    /** تأیید مدیر مالی. */
    public function approveByFinance(int $userId, bool $needsCeo): void
    {
        if ($this->status !== PurchaseOrderStatus::PendingFinance) {
            throw new \DomainException('سفارش در انتظار تأیید مالی نیست.');
        }

        $this->financeApprovedBy = $userId;

        if ($needsCeo) {
            $this->transitionTo(PurchaseOrderStatus::PendingCeo);
        } else {
            $this->transitionTo(PurchaseOrderStatus::Approved);
            $this->finalizeApproval($userId);
        }
        $this->touch();
    }

    /** تأیید مدیرعامل. */
    public function approveByCeo(int $userId): void
    {
        if ($this->status !== PurchaseOrderStatus::PendingCeo) {
            throw new \DomainException('سفارش در انتظار تأیید مدیرعامل نیست.');
        }

        $this->ceoApprovedBy = $userId;
        $this->transitionTo(PurchaseOrderStatus::Approved);
        $this->finalizeApproval($userId);
        $this->touch();
    }

    public function reject(int $userId, string $reason): void
    {
        if (!$this->status->isAwaitingApproval()) {
            throw new \DomainException('فقط سفارش در انتظار تأیید رد می‌شود.');
        }
        if (\trim($reason) === '') {
            throw new \DomainException('دلیل رد الزامی است.');
        }

        $this->rejectReason = \trim($reason);
        $this->transitionTo(PurchaseOrderStatus::Rejected);
        $this->touch();
    }

    public function markReceived(): void
    {
        $this->transitionTo(PurchaseOrderStatus::Received);
        $this->touch();
    }

    public function markInvoiced(): void
    {
        $this->transitionTo(PurchaseOrderStatus::Invoiced);
        $this->touch();
    }

    public function markPaid(): void
    {
        $this->transitionTo(PurchaseOrderStatus::Paid);
        $this->touch();
    }

    public function cancel(): void
    {
        $this->transitionTo(PurchaseOrderStatus::Canceled);
        $this->touch();
    }

    /** مقدار دریافت‌شدهٔ یک قلم را ثبت می‌کند (رسید). */
    public function receiveItem(string $itemId, float $qty): void
    {
        foreach ($this->items as $item) {
            if ($item->itemId() === $itemId) {
                $item->addReceived($qty);

                return;
            }
        }

        throw new \DomainException('قلم در سفارش خرید یافت نشد: ' . $itemId);
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function poNumber(): string
    {
        return $this->poNumber;
    }

    public function supplierId(): string
    {
        return $this->supplierId;
    }

    public function status(): PurchaseOrderStatus
    {
        return $this->status;
    }

    public function isApproved(): bool
    {
        return \in_array($this->status, [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::Received,
            PurchaseOrderStatus::Invoiced,
            PurchaseOrderStatus::Paid,
        ], true);
    }

    public function total(): int
    {
        return $this->total;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function workflowInstanceId(): ?string
    {
        return $this->workflowInstanceId;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function financeApprovedBy(): ?int
    {
        return $this->financeApprovedBy;
    }

    public function ceoApprovedBy(): ?int
    {
        return $this->ceoApprovedBy;
    }

    public function approvedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function rejectReason(): ?string
    {
        return $this->rejectReason;
    }

    /** @return list<PurchaseOrderItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->poNumber,
            'supplier_id' => $this->supplierId,
            'status' => $this->status->value,
            'total' => $this->total,
            'currency' => $this->currency,
            'workflow_instance_id' => $this->workflowInstanceId,
            'created_by' => $this->createdBy,
            'finance_approved_by' => $this->financeApprovedBy,
            'ceo_approved_by' => $this->ceoApprovedBy,
            'approved_at' => self::fmt($this->approvedAt),
            'reject_reason' => $this->rejectReason,
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function finalizeApproval(?int $approverId): void
    {
        $this->approvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->record(new PurchaseOrderWasApproved(
            $this->id,
            $this->poNumber,
            $this->supplierId,
            $this->total,
            $this->currency,
            $this->financeApprovedBy,
            $this->ceoApprovedBy,
            $this->workflowInstanceId,
        ));
    }

    private function transitionTo(PurchaseOrderStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت سفارش خرید نامجاز: %s → %s (سفارش %s)',
                $this->status->value,
                $target->value,
                $this->poNumber,
            ));
        }

        $this->status = $target;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    private static function fmt(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
