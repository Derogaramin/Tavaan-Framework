<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * StockMovement — حرکت موجودی append-only (DOC-097 §2.2).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Enums\StockMovementType;

/**
 * 🔒 append-only — حرکت هرگز ویرایش/حذف نمی‌شود (گردش کالا قابل حسابرسی).
 *
 * @api 1.0
 */
final class StockMovement
{
    private function __construct(
        private readonly string $id,
        private readonly string $warehouseId,
        private readonly string $itemId,
        private readonly StockMovementType $type,
        private readonly float $quantity,
        private readonly int $unitCost,
        private readonly ?string $referenceType,
        private readonly ?string $referenceId,
        private readonly ?string $voucherId,
        private readonly ?int $userId,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        string $warehouseId,
        string $itemId,
        StockMovementType $type,
        float $quantity,
        int $unitCost = 0,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $voucherId = null,
        ?int $userId = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if ($quantity <= 0) {
            throw new \DomainException('مقدار حرکت موجودی باید مثبت باشد (جهت از نوع حرکت است).');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $warehouseId,
            $itemId,
            $type,
            \round($quantity, 3),
            \max(0, $unitCost),
            $referenceType,
            $referenceId,
            $voucherId,
            $userId,
            $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['warehouse_id'] ?? ''),
            (string) ($row['item_id'] ?? ''),
            StockMovementType::tryFrom((string) ($row['type'] ?? 'receipt')) ?? StockMovementType::Receipt,
            (float) ($row['quantity'] ?? 0),
            (int) ($row['unit_cost'] ?? 0),
            isset($row['reference_type']) && $row['reference_type'] !== null ? (string) $row['reference_type'] : null,
            isset($row['reference_id']) && $row['reference_id'] !== null && $row['reference_id'] !== '' ? (string) $row['reference_id'] : null,
            isset($row['voucher_id']) && $row['voucher_id'] !== null && $row['voucher_id'] !== '' ? (string) $row['voucher_id'] : null,
            isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            self::date($row['created_at'] ?? null) ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function warehouseId(): string
    {
        return $this->warehouseId;
    }

    public function itemId(): string
    {
        return $this->itemId;
    }

    public function type(): StockMovementType
    {
        return $this->type;
    }

    public function quantity(): float
    {
        return $this->quantity;
    }

    /** مقدار با علامت (مثبت = ورود، منفی = خروج). */
    public function signedQuantity(): float
    {
        if ($this->type->increases()) {
            return $this->quantity;
        }

        return $this->type->decreases() ? -$this->quantity : $this->quantity;
    }

    public function unitCost(): int
    {
        return $this->unitCost;
    }

    public function totalValue(): int
    {
        return (int) \round($this->quantity * $this->unitCost);
    }

    public function referenceType(): ?string
    {
        return $this->referenceType;
    }

    public function referenceId(): ?string
    {
        return $this->referenceId;
    }

    public function voucherId(): ?string
    {
        return $this->voucherId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouseId,
            'item_id' => $this->itemId,
            'type' => $this->type->value,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unitCost,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'voucher_id' => $this->voucherId,
            'user_id' => $this->userId,
            'created_at' => $this->createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
        ];
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
