<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * DatabaseProductionRepository — پایداری بافتهٔ تولید.
 */

namespace Modules\Erp\Infrastructure\Persistence;

use Modules\Erp\Domain\Entities\Bom;
use Modules\Erp\Domain\Entities\WorkOrder;
use Modules\Erp\Domain\Repositories\ProductionRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Time\Clock;

/**
 * @internal
 */
final class DatabaseProductionRepository implements ProductionRepositoryInterface
{
    public function boms(?string $productId = null): array
    {
        $query = DB::table('erp_bom');
        if ($productId !== null && $productId !== '') {
            $query->where('product_id', '=', $productId);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return \array_map(static fn (array $row): Bom => Bom::fromRow($row), $rows);
    }

    public function findBom(string $id): ?Bom
    {
        $row = DB::table('erp_bom')->where('id', '=', $id)->first();

        return $row !== null ? Bom::fromRow($row) : null;
    }

    public function findActiveBomFor(string $productId): ?Bom
    {
        $row = DB::table('erp_bom')
            ->where('product_id', '=', $productId)
            ->where('is_active', '=', true)
            ->orderByDesc('version')
            ->first();

        return $row !== null ? Bom::fromRow($row) : null;
    }

    public function saveBom(Bom $bom): void
    {
        $exists = DB::table('erp_bom')->where('id', '=', $bom->id())->count() > 0;
        $row = $bom->toRow();

        if ($exists) {
            unset($row['created_at']);
            $row['updated_at'] = Clock::utcNow()->format('Y-m-d H:i:s.v');
            DB::table('erp_bom')->where('id', '=', $bom->id())->update($row);

            return;
        }

        unset($row['updated_at']);
        DB::table('erp_bom')->insert($row);
    }

    public function findWorkOrder(string $id): ?WorkOrder
    {
        $row = DB::table('erp_work_orders')->where('id', '=', $id)->first();

        return $row !== null ? WorkOrder::fromRow($row) : null;
    }

    public function workOrders(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table('erp_work_orders');

        foreach (['status', 'product_id'] as $key) {
            $value = $filters[$key] ?? null;
            if (\is_string($value) && $value !== '') {
                $query->where($key, '=', $value);
            }
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->offset($offset)->get();

        return \array_map(static fn (array $row): WorkOrder => WorkOrder::fromRow($row), $rows);
    }

    public function countWorkOrders(array $filters = []): int
    {
        $query = DB::table('erp_work_orders');

        foreach (['status', 'product_id'] as $key) {
            $value = $filters[$key] ?? null;
            if (\is_string($value) && $value !== '') {
                $query->where($key, '=', $value);
            }
        }

        return $query->count();
    }

    public function saveWorkOrder(WorkOrder $order): void
    {
        $exists = DB::table('erp_work_orders')->where('id', '=', $order->id())->count() > 0;
        $row = $order->toRow();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('erp_work_orders')->where('id', '=', $order->id())->update($row);

            return;
        }

        DB::table('erp_work_orders')->insert($row);
    }
}
