<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * WorkOrder — سفارش تولید (DOC-097 §2.4).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Enums\WorkOrderStatus;
use Modules\Erp\Domain\Events\WorkOrderWasCompleted;

/**
 * WorkOrder — مصرف مواد (حواله) + تولید محصول (رسید) + بهای تمام‌شده.
 *
 * @api 1.0
 */
final class WorkOrder
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private readonly string $woNumber,
        private readonly string $productId,
        private readonly string $bomId,
        private readonly string $warehouseId,
        private readonly float $plannedQty,
        private float $producedQty,
        private int $materialCost,
        private int $unitCost,
        private WorkOrderStatus $status,
        private ?string $voucherId,
        private readonly ?int $createdBy,
        private ?\DateTimeImmutable $startedAt,
        private ?\DateTimeImmutable $completedAt,
        private readonly ?\DateTimeImmutable $createdAt,
    ) {
    }

    public static function plan(string $woNumber, string $productId, string $bomId, string $warehouseId, float $plannedQty, ?int $createdBy = null, ?\DateTimeImmutable $at = null): self
    {
        if ($plannedQty <= 0) {
            throw new \DomainException('مقدار برنامه‌ریزی‌شده باید مثبت باشد.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $woNumber,
            $productId,
            $bomId,
            $warehouseId,
            \round($plannedQty, 3),
            0.0,
            0,
            0,
            WorkOrderStatus::Planned,
            null,
            $createdBy,
            null,
            null,
            $now,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['wo_number'] ?? ''),
            (string) ($row['product_id'] ?? ''),
            (string) ($row['bom_id'] ?? ''),
            (string) ($row['warehouse_id'] ?? ''),
            (float) ($row['planned_qty'] ?? 0),
            (float) ($row['produced_qty'] ?? 0),
            (int) ($row['material_cost'] ?? 0),
            (int) ($row['unit_cost'] ?? 0),
            WorkOrderStatus::tryFrom((string) ($row['status'] ?? 'planned')) ?? WorkOrderStatus::Planned,
            isset($row['voucher_id']) && $row['voucher_id'] !== null && $row['voucher_id'] !== '' ? (string) $row['voucher_id'] : null,
            isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            self::date($row['started_at'] ?? null),
            self::date($row['completed_at'] ?? null),
            self::date($row['created_at'] ?? null),
        );
    }

    public function start(): void
    {
        $this->transitionTo(WorkOrderStatus::InProgress);
        $this->startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /** بهای مواد مصرف‌رفته (جمع حواله‌ها — توسط سرویس محاسبه می‌شود). */
    public function applyMaterialCost(int $totalMaterialCost): void
    {
        $this->materialCost = \max(0, $totalMaterialCost);
    }

    /**
     * تکمیل تولید — qty تولیدشده + بهای هر واحد.
     */
    public function complete(float $producedQty, ?string $voucherId = null): void
    {
        if ($producedQty <= 0) {
            throw new \DomainException('مقدار تولیدشده باید مثبت باشد.');
        }

        $this->transitionTo(WorkOrderStatus::Completed);
        $this->producedQty = \round($producedQty, 3);
        $this->unitCost = (int) \round($this->materialCost / $this->producedQty);
        $this->voucherId ??= $voucherId;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->record(new WorkOrderWasCompleted(
            $this->id,
            $this->woNumber,
            $this->productId,
            $this->producedQty,
            $this->materialCost,
            $this->unitCost,
            $this->voucherId,
        ));
    }

    public function cancel(): void
    {
        $this->transitionTo(WorkOrderStatus::Canceled);
    }

    public function attachVoucher(string $voucherId): void
    {
        $this->voucherId ??= $voucherId;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function woNumber(): string
    {
        return $this->woNumber;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function bomId(): string
    {
        return $this->bomId;
    }

    public function warehouseId(): string
    {
        return $this->warehouseId;
    }

    public function plannedQty(): float
    {
        return $this->plannedQty;
    }

    public function producedQty(): float
    {
        return $this->producedQty;
    }

    public function materialCost(): int
    {
        return $this->materialCost;
    }

    public function unitCost(): int
    {
        return $this->unitCost;
    }

    public function status(): WorkOrderStatus
    {
        return $this->status;
    }

    public function voucherId(): ?string
    {
        return $this->voucherId;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
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
            'wo_number' => $this->woNumber,
            'product_id' => $this->productId,
            'bom_id' => $this->bomId,
            'warehouse_id' => $this->warehouseId,
            'planned_qty' => $this->plannedQty,
            'produced_qty' => $this->producedQty,
            'material_cost' => $this->materialCost,
            'unit_cost' => $this->unitCost,
            'status' => $this->status->value,
            'voucher_id' => $this->voucherId,
            'created_by' => $this->createdBy,
            'started_at' => self::fmt($this->startedAt),
            'completed_at' => self::fmt($this->completedAt),
            'created_at' => self::fmt($this->createdAt),
        ];
    }

    private function transitionTo(WorkOrderStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت سفارش تولید نامجاز: %s → %s (%s)',
                $this->status->value,
                $target->value,
                $this->woNumber,
            ));
        }

        $this->status = $target;
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
