<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * DatabaseInventoryRepository — پایداری بافتهٔ انبار.
 */

namespace Modules\Erp\Infrastructure\Persistence;

use Modules\Erp\Domain\Entities\InventoryItem;
use Modules\Erp\Domain\Entities\StockMovement;
use Modules\Erp\Domain\Entities\Warehouse;
use Modules\Erp\Domain\Repositories\InventoryRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Time\Clock;

/**
 * @internal
 */
final class DatabaseInventoryRepository implements InventoryRepositoryInterface
{
    // ── انبارها ────────────────────────────────────────────────────────────

    public function warehouses(): array
    {
        $rows = DB::table('erp_warehouses')->orderBy('code')->get();

        return \array_map(static fn (array $row): Warehouse => Warehouse::fromRow($row), $rows);
    }

    public function findWarehouse(string $id): ?Warehouse
    {
        $row = DB::table('erp_warehouses')->where('id', '=', $id)->first();

        return $row !== null ? Warehouse::fromRow($row) : null;
    }

    public function saveWarehouse(Warehouse $warehouse): void
    {
        $exists = DB::table('erp_warehouses')->where('id', '=', $warehouse->id())->count() > 0;
        $row = $warehouse->toRow();

        if ($exists) {
            unset($row['created_at']);
            $row['updated_at'] = Clock::utcNow()->format('Y-m-d H:i:s.v');
            DB::table('erp_warehouses')->where('id', '=', $warehouse->id())->update($row);

            return;
        }

        DB::table('erp_warehouses')->insert($row);
    }

    // ── کالاها ─────────────────────────────────────────────────────────────

    public function items(?string $kind = null, ?string $q = null): array
    {
        $query = DB::table('erp_inventory_items');

        if ($kind !== null && $kind !== '') {
            $query->where('kind', '=', $kind);
        }
        if ($q !== null && \trim($q) !== '') {
            $needle = '%' . \trim($q) . '%';
            $query->where(static function (mixed $inner) use ($needle): void {
                $inner->where('name', 'like', $needle)->orWhere('sku', 'like', $needle);
            });
        }

        $rows = $query->orderBy('sku')->get();

        return \array_map(static fn (array $row): InventoryItem => InventoryItem::fromRow($row), $rows);
    }

    public function findItem(string $id): ?InventoryItem
    {
        $row = DB::table('erp_inventory_items')->where('id', '=', $id)->first();

        return $row !== null ? InventoryItem::fromRow($row) : null;
    }

    public function findItemBySku(string $sku): ?InventoryItem
    {
        $row = DB::table('erp_inventory_items')->where('sku', '=', $sku)->first();

        return $row !== null ? InventoryItem::fromRow($row) : null;
    }

    public function saveItem(InventoryItem $item): void
    {
        $exists = DB::table('erp_inventory_items')->where('id', '=', $item->id())->count() > 0;
        $row = $item->toRow();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            $row['updated_at'] = Clock::utcNow()->format('Y-m-d H:i:s.v');
            DB::table('erp_inventory_items')->where('id', '=', $item->id())->update($row);

            return;
        }

        DB::table('erp_inventory_items')->insert($row);
    }

    // ── موجودی ─────────────────────────────────────────────────────────────

    public function stockOf(string $warehouseId, string $itemId): array
    {
        $row = DB::table('erp_stock')
            ->where('warehouse_id', '=', $warehouseId)
            ->where('item_id', '=', $itemId)
            ->first();

        return [
            'quantity' => $row !== null ? (float) $row['quantity'] : 0.0,
            'reserved' => $row !== null ? (float) $row['reserved'] : 0.0,
        ];
    }

    public function totalQuantityOf(string $itemId): float
    {
        $total = DB::table('erp_stock')->where('item_id', '=', $itemId)->sum('quantity');

        return \round((float) ($total ?? 0), 3);
    }

    public function updateStock(string $warehouseId, string $itemId, float $quantity, float $reserved = 0.0): void
    {
        $exists = DB::table('erp_stock')
            ->where('warehouse_id', '=', $warehouseId)
            ->where('item_id', '=', $itemId)
            ->count() > 0;

        if ($exists) {
            DB::table('erp_stock')
                ->where('warehouse_id', '=', $warehouseId)
                ->where('item_id', '=', $itemId)
                ->update(['quantity' => $quantity, 'reserved' => $reserved]);

            return;
        }

        DB::table('erp_stock')->insert([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'quantity' => $quantity,
            'reserved' => $reserved,
        ]);
    }

    // ── حرکات (append-only) ────────────────────────────────────────────────

    public function addMovement(StockMovement $movement): void
    {
        DB::table('erp_stock_movements')->insert($movement->toRow());
    }

    public function addMovements(array $movements): void
    {
        foreach ($movements as $movement) {
            $this->addMovement($movement);
        }
    }

    public function movements(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $query = DB::table('erp_stock_movements');
        $this->applyMovementFilters($query, $filters);

        $rows = $query->orderByDesc('created_at')->limit($limit)->offset($offset)->get();

        return \array_map(static fn (array $row): StockMovement => StockMovement::fromRow($row), $rows);
    }

    public function countMovements(array $filters = []): int
    {
        $query = DB::table('erp_stock_movements');
        $this->applyMovementFilters($query, $filters);

        return $query->count();
    }

    public function turnover(?string $dateFrom = null, ?string $dateTo = null, ?string $warehouseId = null): array
    {
        $query = DB::table('erp_stock_movements');

        if ($dateFrom !== null && $dateFrom !== '') {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00.000');
        }
        if ($dateTo !== null && $dateTo !== '') {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59.999');
        }
        if ($warehouseId !== null && $warehouseId !== '') {
            $query->where('warehouse_id', '=', $warehouseId);
        }

        // جمع per (item, type) — ترکیب در PHP (بدون raw)
        $rows = $query->select('item_id', 'type', 'quantity')->get();

        $byItem = [];
        foreach ($rows as $row) {
            $itemId = (string) $row['item_id'];
            $byItem[$itemId] ??= ['item_id' => $itemId, 'received' => 0.0, 'issued' => 0.0];

            $qty = (float) $row['quantity'];
            if (\in_array((string) $row['type'], ['receipt', 'transfer_in'], true)) {
                $byItem[$itemId]['received'] += $qty;
            } elseif (\in_array((string) $row['type'], ['issue', 'transfer_out'], true)) {
                $byItem[$itemId]['issued'] += $qty;
            }
        }

        $out = [];
        foreach ($byItem as $row) {
            $out[] = [
                'item_id' => $row['item_id'],
                'received' => \round($row['received'], 3),
                'issued' => \round($row['issued'], 3),
            ];
        }

        return $out;
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $filters */
    private function applyMovementFilters(mixed $query, array $filters): void
    {
        foreach (['warehouse_id', 'item_id', 'type', 'reference_type', 'reference_id'] as $key) {
            $value = $filters[$key] ?? null;
            if (\is_string($value) && $value !== '') {
                $query->where($key, '=', $value);
            }
        }
    }
}
