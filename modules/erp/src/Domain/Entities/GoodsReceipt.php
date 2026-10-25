<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * GoodsReceipt — رسید کالا (DOC-097 §2.3: PO → رسید انبار).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Events\GoodsReceiptWasRegistered;

/**
 * @api 1.0
 */
final class GoodsReceipt
{
    /** @var list<object> */
    private array $recordedEvents = [];

    /** @var list<array{item_id: string, qty: float, unit_cost: int}> */
    private array $items;

    private function __construct(
        private readonly string $id,
        private readonly string $receiptNumber,
        private readonly ?string $poId,
        private readonly ?string $supplierId,
        private readonly string $warehouseId,
        array $items,
        private readonly ?int $receivedBy,
        private int $totalCost,
        private ?string $voucherId,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->items = $items;
    }

    /** @param list<array{item_id: string, qty: float, unit_cost: int}> $items */
    public static function create(
        string $receiptNumber,
        string $warehouseId,
        array $items,
        ?string $poId = null,
        ?string $supplierId = null,
        ?int $receivedBy = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if ($items === []) {
            throw new \DomainException('رسید بدون قلم ثبت نمی‌شود.');
        }

        $total = 0;
        foreach ($items as $line) {
            if ((float) ($line['qty'] ?? 0) <= 0) {
                throw new \DomainException('مقدار رسید باید مثبت باشد.');
            }
            $total += (int) \round((float) $line['qty'] * (int) ($line['unit_cost'] ?? 0));
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $receiptNumber,
            $poId,
            $supplierId,
            $warehouseId,
            $items,
            $receivedBy,
            $total,
            null,
            $now,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $items = [];
        $raw = $row['items'] ?? null;
        if (\is_string($raw) && $raw !== '') {
            $decoded = \json_decode($raw, true);
            $items = \is_array($decoded) ? $decoded : [];
        } elseif (\is_array($raw)) {
            $items = $raw;
        }

        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['receipt_number'] ?? ''),
            isset($row['po_id']) && $row['po_id'] !== null && $row['po_id'] !== '' ? (string) $row['po_id'] : null,
            isset($row['supplier_id']) && $row['supplier_id'] !== null && $row['supplier_id'] !== '' ? (string) $row['supplier_id'] : null,
            (string) ($row['warehouse_id'] ?? ''),
            $items,
            isset($row['received_by']) && $row['received_by'] !== null ? (int) $row['received_by'] : null,
            (int) ($row['total_cost'] ?? 0),
            isset($row['voucher_id']) && $row['voucher_id'] !== null && $row['voucher_id'] !== '' ? (string) $row['voucher_id'] : null,
            isset($row['created_at']) ? new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')) : new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    public function attachVoucher(string $voucherId): void
    {
        $this->voucherId ??= $voucherId;
    }

    public function recordRegistered(): void
    {
        $this->record(new GoodsReceiptWasRegistered($this->id, $this->receiptNumber, $this->poId, $this->warehouseId, $this->totalCost, $this->voucherId));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function receiptNumber(): string
    {
        return $this->receiptNumber;
    }

    public function poId(): ?string
    {
        return $this->poId;
    }

    public function supplierId(): ?string
    {
        return $this->supplierId;
    }

    public function warehouseId(): string
    {
        return $this->warehouseId;
    }

    /** @return list<array{item_id: string, qty: float, unit_cost: int}> */
    public function items(): array
    {
        return $this->items;
    }

    public function receivedBy(): ?int
    {
        return $this->receivedBy;
    }

    public function totalCost(): int
    {
        return $this->totalCost;
    }

    public function voucherId(): ?string
    {
        return $this->voucherId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
            'receipt_number' => $this->receiptNumber,
            'po_id' => $this->poId,
            'supplier_id' => $this->supplierId,
            'warehouse_id' => $this->warehouseId,
            'items' => (string) \json_encode($this->items, JSON_UNESCAPED_UNICODE),
            'received_by' => $this->receivedBy,
            'total_cost' => $this->totalCost,
            'voucher_id' => $this->voucherId,
            'created_at' => $this->createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
        ];
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
