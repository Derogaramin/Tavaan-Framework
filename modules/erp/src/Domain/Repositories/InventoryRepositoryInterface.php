<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * InventoryRepositoryInterface — پورت بافتهٔ انبار.
 */

namespace Modules\Erp\Domain\Repositories;

use Modules\Erp\Domain\Entities\InventoryItem;
use Modules\Erp\Domain\Entities\StockMovement;
use Modules\Erp\Domain\Entities\Warehouse;

/**
 * @api 1.0
 */
interface InventoryRepositoryInterface
{
    // ── انبارها ────────────────────────────────────────────────────────────

    /** @return list<Warehouse> */
    public function warehouses(): array;

    public function findWarehouse(string $id): ?Warehouse;

    public function saveWarehouse(Warehouse $warehouse): void;

    // ── کالاها ─────────────────────────────────────────────────────────────

    /** @return list<InventoryItem> */
    public function items(?string $kind = null, ?string $q = null): array;

    public function findItem(string $id): ?InventoryItem;

    public function findItemBySku(string $sku): ?InventoryItem;

    public function saveItem(InventoryItem $item): void;

    // ── موجودی ─────────────────────────────────────────────────────────────

    /** @return array{quantity: float, reserved: float} */
    public function stockOf(string $warehouseId, string $itemId): array;

    /** موجودی کل یک کالا در همهٔ انبارها. */
    public function totalQuantityOf(string $itemId): float;

    public function updateStock(string $warehouseId, string $itemId, float $quantity, float $reserved = 0.0): void;

    // ── حرکات (append-only) ────────────────────────────────────────────────

    public function addMovement(StockMovement $movement): void;

    /** @param list<StockMovement> $movements */
    public function addMovements(array $movements): void;

    /**
     * @return list<StockMovement>
     * @param array{warehouse_id?: string, item_id?: string, type?: string, reference_type?: string, reference_id?: string} $filters
     */
    public function movements(array $filters = [], int $limit = 100, int $offset = 0): array;

    public function countMovements(array $filters = []): int;

    /**
     * گردش کالا — جمع ورود/خروج per item در بازه.
     *
     * @return list<array{item_id: string, received: float, issued: float}>
     */
    public function turnover(?string $dateFrom = null, ?string $dateTo = null, ?string $warehouseId = null): array;
}
