<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ProductionRepositoryInterface — پورت بافتهٔ تولید.
 */

namespace Modules\Erp\Domain\Repositories;

use Modules\Erp\Domain\Entities\Bom;
use Modules\Erp\Domain\Entities\WorkOrder;

/**
 * @api 1.0
 */
interface ProductionRepositoryInterface
{
    /** @return list<Bom> */
    public function boms(?string $productId = null): array;

    public function findBom(string $id): ?Bom;

    /** BOM فعال محصول. */
    public function findActiveBomFor(string $productId): ?Bom;

    public function saveBom(Bom $bom): void;

    public function findWorkOrder(string $id): ?WorkOrder;

    /**
     * @return list<WorkOrder>
     * @param array{status?: string, product_id?: string} $filters
     */
    public function workOrders(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function countWorkOrders(array $filters = []): int;

    public function saveWorkOrder(WorkOrder $order): void;
}
