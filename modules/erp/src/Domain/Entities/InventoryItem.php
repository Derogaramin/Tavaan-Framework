<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * InventoryItem — کالای انبار با بهای میانگین موزون (DOC-097 §2.2).
 */

namespace Modules\Erp\Domain\Entities;

/**
 * InventoryItem — valuation: weighted_avg (پیش‌فرض config).
 *
 * 🔒 avg_cost = میانگین موزون int minor-unit — در رسید جدید:
 * `new_avg = (on_hand_value + receipt_value) / (on_hand_qty + receipt_qty)`
 * با گرد‌کردن half-up؛ حواله همیشه با avg جاری (بدون تغییر آن).
 *
 * @api 1.0
 */
final class InventoryItem
{
    private function __construct(
        private readonly string $id,
        private readonly string $sku,
        private string $name,
        private string $unit,
        private float $reorderPoint,
        private float $reorderQty,
        private string $valuationMethod,
        private int $avgCost,
        private string $kind,
        private bool $isSold,
    ) {
    }

    public static function create(
        string $sku,
        string $name,
        string $unit = 'عدد',
        float $reorderPoint = 0.0,
        float $reorderQty = 0.0,
        string $kind = 'merchandise',
        int $initialAvgCost = 0,
        bool $isSold = true,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($sku) === '' || \trim($name) === '') {
            throw new \DomainException('SKU و نام کالا الزامی است.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            \trim($sku),
            \trim($name),
            \trim($unit) !== '' ? \trim($unit) : 'عدد',
            \max(0.0, $reorderPoint),
            \max(0.0, $reorderQty),
            'weighted_avg',
            \max(0, $initialAvgCost),
            $kind,
            $isSold,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['sku'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['unit'] ?? 'عدد'),
            (float) ($row['reorder_point'] ?? 0),
            (float) ($row['reorder_qty'] ?? 0),
            (string) ($row['valuation_method'] ?? 'weighted_avg'),
            (int) ($row['avg_cost'] ?? 0),
            (string) ($row['kind'] ?? 'merchandise'),
            (bool) ($row['is_sold'] ?? true),
        );
    }

    /**
     * به‌روزرسانی میانگین موزون هنگام رسید (weighted average).
     *
     * @param float $onHandQty موجودی فعلی (همهٔ انبارها برای سادگیٔ v1 — per-item)
     */
    public function applyReceiptToAverage(float $onHandQty, float $receiptQty, int $receiptUnitCost): void
    {
        $onHandQty = \max(0.0, $onHandQty);
        $receiptQty = \max(0.0, $receiptQty);
        $totalQty = $onHandQty + $receiptQty;

        if ($totalQty <= 0.0) {
            $this->avgCost = \max(0, $receiptUnitCost);

            return;
        }

        $totalValue = ($onHandQty * $this->avgCost) + ($receiptQty * $receiptUnitCost);
        $this->avgCost = (int) \round($totalValue / $totalQty);
    }

    public function revise(?string $name = null, ?float $reorderPoint = null, ?float $reorderQty = null): void
    {
        $this->name = $name !== null && \trim($name) !== '' ? \trim($name) : $this->name;
        $this->reorderPoint = $reorderPoint === null ? $this->reorderPoint : \max(0.0, $reorderPoint);
        $this->reorderQty = $reorderQty === null ? $this->reorderQty : \max(0.0, $reorderQty);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function sku(): string
    {
        return $this->sku;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function reorderPoint(): float
    {
        return $this->reorderPoint;
    }

    public function reorderQty(): float
    {
        return $this->reorderQty;
    }

    public function valuationMethod(): string
    {
        return $this->valuationMethod;
    }

    public function avgCost(): int
    {
        return $this->avgCost;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function isSold(): bool
    {
        return $this->isSold;
    }

    public function isRawMaterial(): bool
    {
        return $this->kind === 'raw_material';
    }

    public function isFinished(): bool
    {
        return $this->kind === 'finished';
    }

    /** موجودی کمتر از نقطهٔ سفارش؟ */
    public function needsReorder(float $availableQty): bool
    {
        return $this->reorderPoint > 0 && $availableQty <= $this->reorderPoint;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'unit' => $this->unit,
            'reorder_point' => $this->reorderPoint,
            'reorder_qty' => $this->reorderQty,
            'valuation_method' => $this->valuationMethod,
            'avg_cost' => $this->avgCost,
            'kind' => $this->kind,
            'is_sold' => $this->isSold,
            'created_at' => $now,
            'updated_at' => null,
        ];
    }
}
