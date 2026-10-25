<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * SalesOrderItem — قلم سفارش فروش (+snapshot بهای COGS).
 */

namespace Modules\Erp\Domain\Entities;

/**
 * @api 1.0
 */
final class SalesOrderItem
{
    private function __construct(
        private readonly string $id,
        private readonly string $orderId,
        private readonly string $itemId,
        private readonly float $qty,
        private float $deliveredQty,
        private readonly int $unitPrice,
        private int $unitCostSnapshot,
    ) {
    }

    public static function create(string $orderId, string $itemId, float $qty, int $unitPrice, ?\DateTimeImmutable $at = null): self
    {
        if ($qty <= 0) {
            throw new \DomainException('مقدار قلم فروش باید مثبت باشد.');
        }
        if ($unitPrice < 0) {
            throw new \DomainException('قیمت فروش نمی‌تواند منفی باشد.');
        }

        return new self(\Tavaan\Support\Ulid::generate($at)->toString(), $orderId, $itemId, \round($qty, 3), 0.0, $unitPrice, 0);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['order_id'] ?? ''),
            (string) ($row['item_id'] ?? ''),
            (float) ($row['qty'] ?? 0),
            (float) ($row['delivered_qty'] ?? 0),
            (int) ($row['unit_price'] ?? 0),
            (int) ($row['unit_cost_snapshot'] ?? 0),
        );
    }

    /** باز-انتساب به سفارش والد (توسط SalesOrder::create). */
    public function withParent(string $orderId): self
    {
        return new self($this->id, $orderId, $this->itemId, $this->qty, $this->deliveredQty, $this->unitPrice, $this->unitCostSnapshot);
    }

    /** ثبت بهای تمام‌شدهٔ لحظهٔ حواله (COGS snapshot). */
    public function snapshotCost(int $unitCost): void
    {
        if ($this->unitCostSnapshot === 0) {
            $this->unitCostSnapshot = \max(0, $unitCost);
        }
    }

    public function addDelivered(float $qty): void
    {
        if ($qty <= 0) {
            throw new \DomainException('مقدار تحویل باید مثبت باشد.');
        }
        if ($this->deliveredQty + $qty > $this->qty + 0.0005) {
            throw new \DomainException('تحویل بیش از مقدار سفارش‌داده‌شده است.');
        }
        $this->deliveredQty = \round($this->deliveredQty + $qty, 3);
    }

    public function lineTotal(): int
    {
        return (int) \round($this->qty * $this->unitPrice);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function itemId(): string
    {
        return $this->itemId;
    }

    public function qty(): float
    {
        return $this->qty;
    }

    public function deliveredQty(): float
    {
        return $this->deliveredQty;
    }

    public function unitPrice(): int
    {
        return $this->unitPrice;
    }

    public function unitCostSnapshot(): int
    {
        return $this->unitCostSnapshot;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'item_id' => $this->itemId,
            'qty' => $this->qty,
            'delivered_qty' => $this->deliveredQty,
            'unit_price' => $this->unitPrice,
            'unit_cost_snapshot' => $this->unitCostSnapshot,
        ];
    }
}
