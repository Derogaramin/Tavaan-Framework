<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * InventoryController — انبار/کالا/حرکات موجودی (/admin/erp/inventory).
 */

namespace Modules\Erp\Http\Controllers\Admin;

use Modules\Erp\Application\UseCases\InventoryService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/erp/README.md
 */
final class InventoryController
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('erp.stock.receive', own: 'erp.warehouse.manage');

        $q = (string) $request->input('q', '');
        $items = $this->service->items(null, $q !== '' ? $q : null);

        $stockByItem = [];
        foreach ($items as $item) {
            $stockByItem[$item->id()] = $this->service->stockByWarehouse($item->id());
        }

        return View::render('erp::admin.inventory-index', [
            'warehouses' => $this->service->warehouses(),
            'items' => $items,
            'stockByItem' => $stockByItem,
            'lowStock' => $this->service->lowStock(),
            'q' => $q,
        ]);
    }

    public function movements(RequestInterface $request): mixed
    {
        $this->authorize('erp.stock.receive', own: 'erp.stock.issue');

        $page = \max(1, (int) $request->input('page', 1));
        $perPage = 30;
        $filters = [];
        foreach (['warehouse_id', 'item_id', 'type', 'reference_type'] as $key) {
            if (($value = (string) $request->input($key, '')) !== '') {
                $filters[$key] = $value;
            }
        }

        $itemNames = [];
        foreach ($this->service->items() as $item) {
            $itemNames[$item->id()] = $item->sku() . ' — ' . $item->name();
        }

        return View::render('erp::admin.movements-index', [
            'movements' => $this->service->movements($filters, $perPage, ($page - 1) * $perPage),
            'total' => $this->service->countMovements($filters),
            'page' => $page,
            'perPage' => $perPage,
            'warehouses' => $this->service->warehouses(),
            'items' => $this->service->items(),
            'itemNames' => $itemNames,
            'filters' => $filters,
        ]);
    }

    public function storeWarehouse(RequestInterface $request): mixed
    {
        $this->authorize('erp.warehouse.manage');

        $validated = Validator::validate($request->all(), [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->service->createWarehouse(
                (string) $validated['code'],
                (string) $validated['name'],
                isset($validated['address']) ? (string) $validated['address'] : null,
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'warehouse_created');

        return redirect('/admin/erp/inventory');
    }

    public function storeItem(RequestInterface $request): mixed
    {
        $this->authorize('erp.warehouse.manage');

        $validated = Validator::validate($request->all(), [
            'sku' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'kind' => ['required', 'in:merchandise,raw_material,finished'],
            'avg_cost' => ['nullable', 'integer', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->service->createItem(
                sku: (string) $validated['sku'],
                name: (string) $validated['name'],
                unit: isset($validated['unit']) && $validated['unit'] !== '' ? (string) $validated['unit'] : 'عدد',
                kind: (string) $validated['kind'],
                initialAvgCost: (int) ($validated['avg_cost'] ?? 0),
                reorderPoint: (float) ($validated['reorder_point'] ?? 0),
                reorderQty: (float) ($validated['reorder_qty'] ?? 0),
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'item_created');

        return redirect('/admin/erp/inventory');
    }

    /** رسید انبار — فرم: warehouse_id + item_id[]/qty[]/unit_cost[] */
    public function storeReceive(RequestInterface $request): mixed
    {
        $this->authorize('erp.stock.receive');

        $warehouseId = (string) $request->input('warehouse_id', '');
        $lines = $this->linesFromRequest($request, withCost: true);
        $referenceType = (string) $request->input('reference_type', 'manual_receipt');

        if ($lines === []) {
            Session::flash('error', 'دست‌کم یک ردیف با مقدار مثبت لازم است.');

            return back();
        }

        try {
            $this->service->receive($warehouseId, $lines, $referenceType, null, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'stock_received');

        return redirect('/admin/erp/inventory/movements');
    }

    /** حوالهٔ انبار. */
    public function storeIssue(RequestInterface $request): mixed
    {
        $this->authorize('erp.stock.issue');

        $warehouseId = (string) $request->input('warehouse_id', '');
        $lines = $this->linesFromRequest($request, withCost: false);
        $referenceType = (string) $request->input('reference_type', 'manual_issue');

        if ($lines === []) {
            Session::flash('error', 'دست‌کم یک ردیف با مقدار مثبت لازم است.');

            return back();
        }

        try {
            $this->service->issue($warehouseId, $lines, $referenceType, null, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'stock_issued');

        return redirect('/admin/erp/inventory/movements');
    }

    public function storeTransfer(RequestInterface $request): mixed
    {
        $this->authorize('erp.stock.adjust');

        $validated = Validator::validate($request->all(), [
            'from_warehouse_id' => ['required', 'string'],
            'to_warehouse_id' => ['required', 'string'],
            'item_id' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $this->service->transfer(
                (string) $validated['from_warehouse_id'],
                (string) $validated['to_warehouse_id'],
                (string) $validated['item_id'],
                (float) $validated['qty'],
                (int) Auth::id(),
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'stock_transferred');

        return redirect('/admin/erp/inventory/movements');
    }

    public function storeAdjust(RequestInterface $request): mixed
    {
        $this->authorize('erp.stock.adjust');

        $validated = Validator::validate($request->all(), [
            'warehouse_id' => ['required', 'string'],
            'item_id' => ['required', 'string'],
            'delta' => ['required', 'numeric'],
        ]);

        $delta = (float) $validated['delta'];

        try {
            $this->service->adjust(
                (string) $validated['warehouse_id'],
                (string) $validated['item_id'],
                $delta,
                (string) $request->input('note', ''),
                (int) Auth::id(),
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'stock_adjusted');

        return redirect('/admin/erp/inventory/movements');
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function linesFromRequest(RequestInterface $request, bool $withCost): array
    {
        $itemIds = (array) $request->input('item_id', []);
        $qtys = (array) $request->input('qty', []);
        $costs = (array) $request->input('unit_cost', []);

        $lines = [];
        foreach ($itemIds as $index => $itemId) {
            $itemId = \trim((string) $itemId);
            $qty = (float) ($qtys[$index] ?? 0);
            if ($itemId === '' || $qty <= 0) {
                continue;
            }

            $line = ['item_id' => $itemId, 'qty' => $qty];
            if ($withCost && isset($costs[$index]) && $costs[$index] !== '') {
                $line['unit_cost'] = (int) $costs[$index];
            }
            $lines[] = $line;
        }

        return $lines;
    }

    private function authorize(string $permission, ?string $own = null): void
    {
        if (Gate::allows($permission)) {
            return;
        }
        if ($own !== null && Gate::allows($own)) {
            return;
        }

        throw new ForbiddenException(messageId: 'erp::messages.forbidden', context: ['permission' => $permission]);
    }
}
