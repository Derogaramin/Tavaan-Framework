<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PurchaseOrderItem — قلم سفارش خرید.
 */

namespace Modules\Erp\Domain\Entities;

/**
 * @api 1.0
 */
final class PurchaseOrderItem
{
    private function __construct(
        private readonly string $id,
        private readonly string $poId,
        private readonly string $itemId,
        private readonly float $qty,
        private float $receivedQty,
        private readonly int $unitPrice,
    ) {
    }

    public static function create(string $poId, string $itemId, float $qty, int $unitPrice, ?\DateTimeImmutable $at = null): self
    {
        if ($qty <= 0) {
            throw new \DomainException('مقدار قلم خرید باید مثبت باشد.');
        }
        if ($unitPrice < 0) {
            throw new \DomainException('قیمت واحد نمی‌تواند منفی باشد.');
        }

        return new self(\Tavaan\Support\Ulid::generate($at)->toString(), $poId, $itemId, \round($qty, 3), 0.0, $unitPrice);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['po_id'] ?? ''),
            (string) ($row['item_id'] ?? ''),
            (float) ($row['qty'] ?? 0),
            (float) ($row['received_qty'] ?? 0),
            (int) ($row['unit_price'] ?? 0),
        );
    }

    /** باز-انتساب به سفارش والد (توسط PurchaseOrder::create). */
    public function withParent(string $poId): self
    {
        return new self($this->id, $poId, $this->itemId, $this->qty, $this->receivedQty, $this->unitPrice);
    }

    public function addReceived(float $qty): void
    {
        if ($qty <= 0) {
            throw new \DomainException('مقدار رسید باید مثبت باشد.');
        }
        if ($this->receivedQty + $qty > $this->qty + 0.0005) {
            throw new \DomainException('رسید بیش از مقدار سفارش‌داده‌شده است.');
        }
        $this->receivedQty = \round($this->receivedQty + $qty, 3);
    }

    public function lineTotal(): int
    {
        return (int) \round($this->qty * $this->unitPrice);
    }

    public function isFullyReceived(): bool
    {
        return $this->receivedQty >= $this->qty - 0.0005;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function poId(): string
    {
        return $this->poId;
    }

    public function itemId(): string
    {
        return $this->itemId;
    }

    public function qty(): float
    {
        return $this->qty;
    }

    public function receivedQty(): float
    {
        return $this->receivedQty;
    }

    public function unitPrice(): int
    {
        return $this->unitPrice;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'po_id' => $this->poId,
            'item_id' => $this->itemId,
            'qty' => $this->qty,
            'received_qty' => $this->receivedQty,
            'unit_price' => $this->unitPrice,
        ];
    }
}
