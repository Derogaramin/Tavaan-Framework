<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ProductionService — تولید: BOM → work order → مصرف → محصول (DOC-097 §2.4).
 */

namespace Modules\Erp\Application\UseCases;

use Modules\Erp\Domain\Entities\Bom;
use Modules\Erp\Domain\Entities\WorkOrder;
use Modules\Erp\Domain\Repositories\InventoryRepositoryInterface;
use Modules\Erp\Domain\Repositories\ProductionRepositoryInterface;
use Modules\Erp\Domain\Services\BomCosting;
use Modules\Erp\Infrastructure\Integration\OutboxPublisher;
use Tavaan\Support\Time\Clock;

/**
 * ProductionService — چرخهٔ تولید:
 *
 * ```
 * BOM → work order(planned) → start → مصرف مواد (حواله → سند WIP/مواد با Outbox)
 * → complete → رسید محصول (→ سند محصول/WIP با Outbox) + بهای تمام‌شده
 * ```
 *
 * @api 1.0
 * @see docs/05-applications/erp.md
 */
final class ProductionService
{
    public function __construct(
        private readonly ProductionRepositoryInterface $repository,
        private readonly InventoryRepositoryInterface $inventoryRepository,
        private readonly InventoryService $inventoryService,
        private readonly BomCosting $bomCosting,
        private readonly OutboxPublisher $outbox,
        private readonly ErpAuditRecorder $audit,
    ) {
    }

    // ── BOM ────────────────────────────────────────────────────────────────

    /**
     * @param list<array{item_id: string, qty: float}> $components
     */
    public function createBom(string $productId, array $components, int $version = 1): Bom
    {
        $product = $this->inventoryRepository->findItem($productId);
        if ($product === null) {
            throw new \DomainException('محصول یافت نشد: ' . $productId);
        }
        foreach ($components as $component) {
            if ($this->inventoryRepository->findItem((string) ($component['item_id'] ?? '')) === null) {
                throw new \DomainException('جزء BOM یافت نشد: ' . (string) ($component['item_id'] ?? ''));
            }
        }

        // نسخهٔ فعال قبلی غیرفعال می‌شود
        $previous = $this->repository->findActiveBomFor($productId);
        if ($previous !== null) {
            $previous->deactivate();
            $this->repository->saveBom($previous);
            $version = \max($version, $previous->version() + 1);
        }

        $bom = Bom::create($productId, $components, $version);
        $this->repository->saveBom($bom);

        return $bom;
    }

    /** @return list<Bom> */
    public function boms(?string $productId = null): array
    {
        return $this->repository->boms($productId);
    }

    public function findBom(string $id): ?Bom
    {
        return $this->repository->findBom($id);
    }

    // ── سفارش تولید ────────────────────────────────────────────────────────

    public function planWorkOrder(string $productId, float $plannedQty, string $warehouseId, ?int $userId = null): WorkOrder
    {
        $bom = $this->repository->findActiveBomFor($productId);
        if ($bom === null) {
            throw new \DomainException('BOM فعالی برای این محصول وجود ندارد.');
        }

        $order = WorkOrder::plan(
            'WO-' . Clock::utcNow()->format('Ymd') . '-' . \strtoupper(\bin2hex(\random_bytes(3))),
            $productId,
            $bom->id(),
            $warehouseId,
            $plannedQty,
            $userId,
        );
        $this->repository->saveWorkOrder($order);

        return $order;
    }

    /**
     * شروع تولید — مصرف مواد (حواله از انبار):
     * سند بدهکار WIP / بستانکار مواد با Outbox؛ material_cost ذخیره می‌شود.
     */
    public function startWorkOrder(string $woId, ?int $userId = null): WorkOrder
    {
        $order = $this->mustFindOrder($woId);
        $bom = $this->repository->findBom($order->bomId());
        if ($bom === null) {
            throw new \DomainException('BOM سفارش تولید یافت نشد.');
        }

        $requirements = $bom->explodeFor($order->plannedQty());

        // بهای مواد با avg جاری (پیش از حواله — حواله avg را تغییر نمی‌دهد)
        $avgCosts = [];
        $availables = [];
        foreach ($requirements as $requirement) {
            $component = $this->inventoryRepository->findItem((string) $requirement['item_id']);
            $avgCosts[(string) $requirement['item_id']] = $component !== null ? $component->avgCost() : 0;
            $availables[(string) $requirement['item_id']] = $this->inventoryRepository->totalQuantityOf((string) $requirement['item_id']);
        }

        $costing = $this->bomCosting->costOf($requirements, $avgCosts, $availables);
        if ($costing['shortages'] !== []) {
            throw new \DomainException('موجودی مواد ناکافی است: ' . \implode('، ', $costing['shortages']));
        }

        $order->start();

        // مصرف مواد — حواله (رویداد Outbox → سند WIP/مواد)
        $lines = [];
        foreach ($requirements as $requirement) {
            $lines[] = ['item_id' => (string) $requirement['item_id'], 'qty' => (float) $requirement['qty']];
        }
        $this->inventoryService->issue($order->warehouseId(), $lines, 'work_order_consume', $order->id(), $userId);

        $order->applyMaterialCost($costing['total_cost']);
        $this->repository->saveWorkOrder($order);

        $this->audit->record('work_order.started', 'erp.work_order', $order->id(), [
            'wo_number' => $order->woNumber(),
            'material_cost' => $costing['total_cost'],
        ], $userId);

        return $order;
    }

    /**
     * تکمیل تولید — رسید محصول با بهای تمام‌شده (material_cost/produced_qty):
     * سند بدهکار محصول / بستانکار WIP با Outbox.
     */
    public function completeWorkOrder(string $woId, float $producedQty, ?int $userId = null): WorkOrder
    {
        $order = $this->mustFindOrder($woId);
        if ($order->status()->value !== 'in_progress') {
            throw new \DomainException('فقط سفارش در حال تولید تکمیل می‌شود (وضعیت: ' . $order->status()->value . ').');
        }

        $unitCost = (int) \round($order->materialCost() / $producedQty);

        // رسید محصول (میانگین موزون محصول با بهای تمام‌شده به‌روز می‌شود)
        $this->inventoryService->receive(
            $order->warehouseId(),
            [['item_id' => $order->productId(), 'qty' => $producedQty, 'unit_cost' => $unitCost]],
            'work_order_output',
            $order->id(),
            $userId,
        );

        $order->complete($producedQty);
        $this->repository->saveWorkOrder($order);

        // رویداد production.order.completed → سند بهای تمام‌شده (listener)
        foreach ($order->pullEvents() as $event) {
            $this->outbox->store($event);
        }
        $this->outbox->flushNow();

        $this->audit->record('work_order.completed', 'erp.work_order', $order->id(), [
            'wo_number' => $order->woNumber(),
            'produced_qty' => $producedQty,
            'unit_cost' => $order->unitCost(),
            'material_cost' => $order->materialCost(),
        ], $userId);

        return $order;
    }

    // ── پرسش ───────────────────────────────────────────────────────────────

    public function findWorkOrder(string $id): ?WorkOrder
    {
        return $this->repository->findWorkOrder($id);
    }

    /**
     * @return list<WorkOrder>
     * @param array<string, mixed> $filters
     */
    public function workOrders(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->repository->workOrders($filters, $limit, $offset);
    }

    public function countWorkOrders(array $filters = []): int
    {
        return $this->repository->countWorkOrders($filters);
    }

    /** برآورد بهای مواد یک BOM (پیش از تولید). */
    public function estimateMaterialCost(string $bomId, float $qty): int
    {
        $bom = $this->repository->findBom($bomId);
        if ($bom === null) {
            throw new \DomainException('BOM یافت نشد: ' . $bomId);
        }

        $avgCosts = [];
        foreach ($bom->explodeFor($qty) as $requirement) {
            $component = $this->inventoryRepository->findItem((string) $requirement['item_id']);
            $avgCosts[(string) $requirement['item_id']] = $component !== null ? $component->avgCost() : 0;
        }

        return $this->bomCosting->costOf($bom->explodeFor($qty), $avgCosts)['total_cost'];
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    private function mustFindOrder(string $id): WorkOrder
    {
        $order = $this->repository->findWorkOrder($id);
        if ($order === null) {
            throw new \DomainException('سفارش تولید یافت نشد: ' . $id);
        }

        return $order;
    }
}
