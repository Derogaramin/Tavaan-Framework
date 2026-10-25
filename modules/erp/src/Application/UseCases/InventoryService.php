<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * InventoryService — انبار: رسید/حواله/انتقال/اصلاح (DOC-097 §2.2).
 */

namespace Modules\Erp\Application\UseCases;

use Modules\Erp\Domain\Entities\InventoryItem;
use Modules\Erp\Domain\Entities\StockMovement;
use Modules\Erp\Domain\Entities\Warehouse;
use Modules\Erp\Domain\Enums\StockMovementType;
use Modules\Erp\Domain\Events\StockWasIssued;
use Modules\Erp\Domain\Events\StockWasReceived;
use Modules\Erp\Domain\Repositories\InventoryRepositoryInterface;
use Modules\Erp\Infrastructure\Integration\OutboxPublisher;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Ulid;

/**
 * InventoryService — موجودی و حرکات append-only.
 *
 * - رسید با بها → به‌روزرسانی میانگین موزون (weighted_avg)
 * - حواله با avg جاری — کمبود موجودی = خطا (کنترل available)
 * - هر رسید/حواله → رویداد Outbox (سند حسابداری توسط listener مصرف‌کننده)
 *
 * @api 1.0
 * @see docs/05-applications/erp.md
 */
final class InventoryService
{
    public function __construct(
        private readonly InventoryRepositoryInterface $repository,
        private readonly OutboxPublisher $outbox,
    ) {
    }

    // ── انبار/کالا ─────────────────────────────────────────────────────────

    public function createWarehouse(string $code, string $name, ?string $address = null): Warehouse
    {
        $warehouse = Warehouse::create($code, $name, $address);
        $this->repository->saveWarehouse($warehouse);

        return $warehouse;
    }

    /** @return list<Warehouse> */
    public function warehouses(): array
    {
        return $this->repository->warehouses();
    }

    public function findWarehouse(string $id): ?Warehouse
    {
        return $this->repository->findWarehouse($id);
    }

    public function createItem(
        string $sku,
        string $name,
        string $unit = 'عدد',
        string $kind = 'merchandise',
        int $initialAvgCost = 0,
        float $reorderPoint = 0.0,
        float $reorderQty = 0.0,
        bool $isSold = true,
    ): InventoryItem {
        if ($this->repository->findItemBySku($sku) !== null) {
            throw new \DomainException('کالایی با این SKU وجود دارد: ' . $sku);
        }

        $item = InventoryItem::create($sku, $name, $unit, $reorderPoint, $reorderQty, $kind, $initialAvgCost, $isSold);
        $this->repository->saveItem($item);

        return $item;
    }

    /** @return list<InventoryItem> */
    public function items(?string $kind = null, ?string $q = null): array
    {
        return $this->repository->items($kind, $q);
    }

    public function findItem(string $id): ?InventoryItem
    {
        return $this->repository->findItem($id);
    }

    public function updateItem(string $itemId, ?string $name = null, ?float $reorderPoint = null, ?float $reorderQty = null): void
    {
        $item = $this->mustFindItem($itemId);
        $item->revise($name, $reorderPoint, $reorderQty);
        $this->repository->saveItem($item);
    }

    // ── حرکات موجودی ───────────────────────────────────────────────────────

    /**
     * رسید انبار (+) — با بهای هر واحد (به‌روزرسانی میانگین موزون).
     *
     * @param list<array{item_id: string, qty: float, unit_cost?: int}> $lines
     * @return string شناسهٔ گروه حرکت (برای رویداد)
     */
    public function receive(string $warehouseId, array $lines, string $referenceType, ?string $referenceId = null, ?int $userId = null): string
    {
        $this->assertLines($lines);
        $groupId = Ulid::generate()->toString();
        $totalCost = 0;
        $eventLines = [];

        DB::transaction(function () use ($warehouseId, $lines, $referenceType, $referenceId, $userId, $groupId, &$totalCost, &$eventLines): void {
            $this->mustFindWarehouse($warehouseId);

            foreach ($lines as $line) {
                $item = $this->mustFindItem((string) $line['item_id']);
                $qty = (float) $line['qty'];
                $unitCost = (int) ($line['unit_cost'] ?? $item->avgCost());

                // به‌روزرسانی میانگین موزون (weighted avg — بر پایهٔ کل موجودی)
                $onHand = $this->repository->totalQuantityOf($item->id());
                $item->applyReceiptToAverage($onHand, $qty, $unitCost);
                $this->repository->saveItem($item);

                $stock = $this->repository->stockOf($warehouseId, $item->id());
                $this->repository->updateStock(
                    $warehouseId,
                    $item->id(),
                    \round($stock['quantity'] + $qty, 3),
                    $stock['reserved'],
                );

                $this->repository->addMovement(StockMovement::record(
                    warehouseId: $warehouseId,
                    itemId: $item->id(),
                    type: StockMovementType::Receipt,
                    quantity: $qty,
                    unitCost: $unitCost,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    userId: $userId,
                ));

                $lineCost = (int) \round($qty * $unitCost);
                $totalCost += $lineCost;
                $eventLines[] = ['item_id' => $item->id(), 'qty' => $qty, 'unit_cost' => $unitCost, 'kind' => $item->kind()];
            }

            $this->outbox->store(new StockWasReceived($groupId, $warehouseId, $eventLines, $totalCost, $referenceType, $referenceId));
        });

        $this->outbox->flushNow();

        return $groupId;
    }

    /**
     * حوالهٔ انبار (−) — با avg جاری کالا؛ کمبود = خطا.
     *
     * @param list<array{item_id: string, qty: float}> $lines
     */
    public function issue(string $warehouseId, array $lines, string $referenceType, ?string $referenceId = null, ?int $userId = null, ?string $voucherId = null): string
    {
        $this->assertLines($lines);
        $groupId = Ulid::generate()->toString();
        $totalCost = 0;
        $eventLines = [];

        DB::transaction(function () use ($warehouseId, $lines, $referenceType, $referenceId, $userId, $voucherId, $groupId, &$totalCost, &$eventLines): void {
            $this->mustFindWarehouse($warehouseId);

            foreach ($lines as $line) {
                $item = $this->mustFindItem((string) $line['item_id']);
                $qty = (float) $line['qty'];

                $stock = $this->repository->stockOf($warehouseId, $item->id());
                $available = \round($stock['quantity'] - $stock['reserved'], 3);
                if ($available + 0.0005 < $qty) {
                    throw new \DomainException(\sprintf(
                        'موجودی ناکافی برای «%s» در انبار: لازم %.3f، موجود %.3f',
                        $item->name(),
                        $qty,
                        $available,
                    ));
                }

                $this->repository->updateStock($warehouseId, $item->id(), \round($stock['quantity'] - $qty, 3), $stock['reserved']);

                $unitCost = $item->avgCost();
                $this->repository->addMovement(StockMovement::record(
                    warehouseId: $warehouseId,
                    itemId: $item->id(),
                    type: StockMovementType::Issue,
                    quantity: $qty,
                    unitCost: $unitCost,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    voucherId: $voucherId,
                    userId: $userId,
                ));

                $lineCost = (int) \round($qty * $unitCost);
                $totalCost += $lineCost;
                $eventLines[] = ['item_id' => $item->id(), 'qty' => $qty, 'unit_cost' => $unitCost, 'kind' => $item->kind()];
            }

            $this->outbox->store(new StockWasIssued($groupId, $warehouseId, $eventLines, $totalCost, $referenceType, $referenceId, $voucherId));
        });

        $this->outbox->flushNow();

        return $groupId;
    }

    /**
     * انتقال بین دو انبار — خروج از مبدأ + ورود به مقصد (بدون اثر ریالی).
     */
    public function transfer(string $fromWarehouseId, string $toWarehouseId, string $itemId, float $qty, ?int $userId = null): void
    {
        if ($qty <= 0) {
            throw new \DomainException('مقدار انتقال باید مثبت باشد.');
        }
        if ($fromWarehouseId === $toWarehouseId) {
            throw new \DomainException('انبار مبدأ و مقصد یکی است.');
        }

        $item = $this->mustFindItem($itemId);

        DB::transaction(function () use ($fromWarehouseId, $toWarehouseId, $item, $qty, $userId): void {
            $this->mustFindWarehouse($fromWarehouseId);
            $this->mustFindWarehouse($toWarehouseId);

            $from = $this->repository->stockOf($fromWarehouseId, $item->id());
            $available = \round($from['quantity'] - $from['reserved'], 3);
            if ($available + 0.0005 < $qty) {
                throw new \DomainException('موجودی ناکافی برای انتقال «' . $item->name() . '».');
            }

            $transferId = Ulid::generate()->toString();

            $this->repository->updateStock($fromWarehouseId, $item->id(), \round($from['quantity'] - $qty, 3), $from['reserved']);
            $this->repository->addMovement(StockMovement::record($fromWarehouseId, $item->id(), StockMovementType::TransferOut, $qty, $item->avgCost(), 'transfer', $transferId, userId: $userId));

            $to = $this->repository->stockOf($toWarehouseId, $item->id());
            $this->repository->updateStock($toWarehouseId, $item->id(), \round($to['quantity'] + $qty, 3), $to['reserved']);
            $this->repository->addMovement(StockMovement::record($toWarehouseId, $item->id(), StockMovementType::TransferIn, $qty, $item->avgCost(), 'transfer', $transferId, userId: $userId));
        });
    }

    /**
     * اصلاح موجودی (شمارش انبار) — ±qty؛ سند حسابداری سود/زیان انبارداری.
     */
    public function adjust(string $warehouseId, string $itemId, float $delta, ?string $note = null, ?int $userId = null): void
    {
        if (\abs($delta) < 0.0005) {
            throw new \DomainException('اصلاح با مقدار صفر بی‌معنا است.');
        }

        $item = $this->mustFindItem($itemId);

        DB::transaction(function () use ($warehouseId, $item, $delta, $userId): void {
            $this->mustFindWarehouse($warehouseId);

            $stock = $this->repository->stockOf($warehouseId, $item->id());
            $newQty = \round($stock['quantity'] + $delta, 3);
            if ($newQty < 0) {
                throw new \DomainException('اصلاح، موجودی «' . $item->name() . '» را منفی می‌کند.');
            }

            $this->repository->updateStock($warehouseId, $item->id(), $newQty, $stock['reserved']);

            $adjustmentId = Ulid::generate()->toString();
            if ($delta > 0) {
                $this->repository->addMovement(StockMovement::record($warehouseId, $item->id(), StockMovementType::Receipt, $delta, $item->avgCost(), 'adjustment', $adjustmentId, userId: $userId));
                $this->outbox->store(new StockWasReceived($adjustmentId, $warehouseId, [['item_id' => $item->id(), 'qty' => $delta, 'unit_cost' => $item->avgCost(), 'kind' => $item->kind()]], (int) \round($delta * $item->avgCost()), 'adjustment', $adjustmentId));
            } else {
                $this->repository->addMovement(StockMovement::record($warehouseId, $item->id(), StockMovementType::Issue, -$delta, $item->avgCost(), 'adjustment', $adjustmentId, userId: $userId));
                $this->outbox->store(new StockWasIssued($adjustmentId, $warehouseId, [['item_id' => $item->id(), 'qty' => -$delta, 'unit_cost' => $item->avgCost(), 'kind' => $item->kind()]], (int) \round(-$delta * $item->avgCost()), 'adjustment', $adjustmentId));
            }
        });

        $this->outbox->flushNow();
    }

    // ── پرسش ───────────────────────────────────────────────────────────────

    /** @return array{quantity: float, reserved: float} */
    public function stockOf(string $warehouseId, string $itemId): array
    {
        return $this->repository->stockOf($warehouseId, $itemId);
    }

    /** موجودی همهٔ انبارها برای یک کالا. @return list<array{warehouse_id: string, warehouse_name: string, quantity: float, reserved: float}> */
    public function stockByWarehouse(string $itemId): array
    {
        $out = [];
        foreach ($this->repository->warehouses() as $warehouse) {
            $stock = $this->repository->stockOf($warehouse->id(), $itemId);
            if ($stock['quantity'] > 0 || $stock['reserved'] > 0) {
                $out[] = [
                    'warehouse_id' => $warehouse->id(),
                    'warehouse_name' => $warehouse->name(),
                    'quantity' => $stock['quantity'],
                    'reserved' => $stock['reserved'],
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<StockMovement>
     * @param array<string, mixed> $filters
     */
    public function movements(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->repository->movements($filters, $limit, $offset);
    }

    public function countMovements(array $filters = []): int
    {
        return $this->repository->countMovements($filters);
    }

    /** کالاهای زیر نقطهٔ سفارش. @return list<array{item: InventoryItem, available: float}> */
    public function lowStock(): array
    {
        $out = [];
        foreach ($this->repository->items() as $item) {
            $available = $this->repository->totalQuantityOf($item->id());
            if ($item->needsReorder($available)) {
                $out[] = ['item' => $item, 'available' => $available];
            }
        }

        return $out;
    }

    /**
     * گردش کالا (گزارش §5).
     *
     * @return list<array{item_id: string, received: float, issued: float}>
     */
    public function turnover(?string $dateFrom = null, ?string $dateTo = null, ?string $warehouseId = null): array
    {
        return $this->repository->turnover($dateFrom, $dateTo, $warehouseId);
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    /** @param list<array<string, mixed>> $lines */
    private function assertLines(array $lines): void
    {
        if ($lines === []) {
            throw new \DomainException('حرکت موجودی بدون ردیف ثبت نمی‌شود.');
        }
        foreach ($lines as $line) {
            if ((float) ($line['qty'] ?? 0) <= 0) {
                throw new \DomainException('مقدار هر ردیف باید مثبت باشد.');
            }
        }
    }

    private function mustFindWarehouse(string $id): Warehouse
    {
        $warehouse = $this->repository->findWarehouse($id);
        if ($warehouse === null) {
            throw new \DomainException('انبار یافت نشد: ' . $id);
        }

        return $warehouse;
    }

    private function mustFindItem(string $id): InventoryItem
    {
        $item = $this->repository->findItem($id);
        if ($item === null) {
            throw new \DomainException('کالا یافت نشد: ' . $id);
        }

        return $item;
    }
}
