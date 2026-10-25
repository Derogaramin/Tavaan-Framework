<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * SalesOrder — سفارش فروش (draft→confirmed→delivered→invoiced).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Enums\SalesOrderStatus;

/**
 * @api 1.0
 */
final class SalesOrder
{
    /** @var list<SalesOrderItem> */
    private array $items;

    private function __construct(
        private readonly string $id,
        private readonly string $orderNumber,
        private readonly string $customerId,
        private readonly string $warehouseId,
        private SalesOrderStatus $status,
        private int $total,
        private ?int $createdBy,
        private ?\DateTimeImmutable $deliveredAt,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        array $items = [],
    ) {
        $this->items = $items;
    }

    /** @param list<SalesOrderItem> $items */
    public static function create(string $orderNumber, string $customerId, string $warehouseId, array $items, ?int $createdBy = null, ?\DateTimeImmutable $at = null): self
    {
        if ($items === []) {
            throw new \DomainException('سفارش فروش بدون قلم ساخته نمی‌شود.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $selfId = \Tavaan\Support\Ulid::generate($now)->toString();

        // 🔒 اقلام به شناسهٔ واقعی سفارش بازشانده می‌شوند
        $items = \array_map(static fn (SalesOrderItem $item): SalesOrderItem => $item->withParent($selfId), $items);

        $total = 0;
        foreach ($items as $item) {
            $total += $item->lineTotal();
        }

        return new self(
            $selfId,
            $orderNumber,
            $customerId,
            $warehouseId,
            SalesOrderStatus::Draft,
            $total,
            $createdBy,
            null,
            $now,
            null,
            $items,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<SalesOrderItem> $items
     */
    public static function fromRow(array $row, array $items = []): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['order_number'] ?? ''),
            (string) ($row['customer_id'] ?? ''),
            (string) ($row['warehouse_id'] ?? ''),
            SalesOrderStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? SalesOrderStatus::Draft,
            (int) ($row['total'] ?? 0),
            isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            self::date($row['delivered_at'] ?? null),
            self::date($row['created_at'] ?? null),
            self::date($row['updated_at'] ?? null),
            $items,
        );
    }

    public function confirm(): void
    {
        $this->transitionTo(SalesOrderStatus::Confirmed);
        $this->touch();
    }

    public function markDelivered(): void
    {
        $this->transitionTo(SalesOrderStatus::Delivered);
        $this->deliveredAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->touch();
    }

    public function markInvoiced(): void
    {
        $this->transitionTo(SalesOrderStatus::Invoiced);
        $this->touch();
    }

    public function cancel(): void
    {
        $this->transitionTo(SalesOrderStatus::Canceled);
        $this->touch();
    }

    public function deliverItem(string $itemId, float $qty): void
    {
        foreach ($this->items as $item) {
            if ($item->itemId() === $itemId) {
                $item->addDelivered($qty);

                return;
            }
        }

        throw new \DomainException('قلم در سفارش فروش یافت نشد: ' . $itemId);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function orderNumber(): string
    {
        return $this->orderNumber;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function warehouseId(): string
    {
        return $this->warehouseId;
    }

    public function status(): SalesOrderStatus
    {
        return $this->status;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function deliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    /** @return list<SalesOrderItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->orderNumber,
            'customer_id' => $this->customerId,
            'warehouse_id' => $this->warehouseId,
            'status' => $this->status->value,
            'total' => $this->total,
            'created_by' => $this->createdBy,
            'delivered_at' => self::fmt($this->deliveredAt),
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
        ];
    }

    private function transitionTo(SalesOrderStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت سفارش فروش نامجاز: %s → %s (سفارش %s)',
                $this->status->value,
                $target->value,
                $this->orderNumber,
            ));
        }

        $this->status = $target;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
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
