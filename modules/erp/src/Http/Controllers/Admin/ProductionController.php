<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ProductionController — تولید (/admin/erp/production).
 */

namespace Modules\Erp\Http\Controllers\Admin;

use Modules\Erp\Application\UseCases\InventoryService;
use Modules\Erp\Application\UseCases\ProductionService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/erp/README.md
 */
final class ProductionController
{
    public function __construct(
        private readonly ProductionService $service,
        private readonly InventoryService $inventory,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('erp.production.manage');

        $page = \max(1, (int) $request->input('page', 1));
        $perPage = 20;

        $itemNames = [];
        foreach ($this->inventory->items() as $item) {
            $itemNames[$item->id()] = $item->sku() . ' — ' . $item->name();
        }

        return View::render('erp::admin.production-index', [
            'workOrders' => $this->service->workOrders([], $perPage, ($page - 1) * $perPage),
            'total' => $this->service->countWorkOrders(),
            'page' => $page,
            'perPage' => $perPage,
            'boms' => $this->service->boms(),
            'items' => $this->inventory->items(),
            'warehouses' => $this->inventory->warehouses(),
            'itemNames' => $itemNames,
        ]);
    }

    public function storeBom(RequestInterface $request): mixed
    {
        $this->authorize('erp.production.manage');

        $validated = Validator::validate($request->all(), [
            'product_id' => ['required', 'string'],
        ]);

        $itemIds = (array) $request->input('component_id', []);
        $qtys = (array) $request->input('component_qty', []);

        $components = [];
        foreach ($itemIds as $index => $itemId) {
            $qty = (float) ($qtys[$index] ?? 0);
            if (\trim((string) $itemId) !== '' && $qty > 0) {
                $components[] = ['item_id' => \trim((string) $itemId), 'qty' => $qty];
            }
        }

        if ($components === []) {
            Session::flash('error', 'BOM بدون جزء ثبت نمی‌شود.');

            return back();
        }

        try {
            $this->service->createBom((string) $validated['product_id'], $components);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'bom_created');

        return redirect('/admin/erp/production');
    }

    public function storeWorkOrder(RequestInterface $request): mixed
    {
        $this->authorize('erp.production.manage');

        $validated = Validator::validate($request->all(), [
            'product_id' => ['required', 'string'],
            'warehouse_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $order = $this->service->planWorkOrder(
                (string) $validated['product_id'],
                (float) $validated['planned_qty'],
                (string) $validated['warehouse_id'],
                (int) Auth::id(),
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'wo_planned');

        return redirect('/admin/erp/production/work-orders/' . $order->id());
    }

    public function showWorkOrder(string $id): mixed
    {
        $this->authorize('erp.production.manage');

        $order = $this->service->findWorkOrder($id);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'erp::messages.not_found');
        }

        $bom = $this->service->findBom($order->bomId());
        $itemNames = [];
        foreach ($this->inventory->items() as $item) {
            $itemNames[$item->id()] = $item->sku() . ' — ' . $item->name();
        }

        return View::render('erp::admin.production-show', [
            'order' => $order,
            'bom' => $bom,
            'itemNames' => $itemNames,
            'product' => $this->inventory->findItem($order->productId()),
        ]);
    }

    public function startWorkOrder(string $id): mixed
    {
        $this->authorize('erp.production.manage');

        try {
            $this->service->startWorkOrder($id, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'wo_started');

        return redirect('/admin/erp/production/work-orders/' . $id);
    }

    public function completeWorkOrder(RequestInterface $request, string $id): mixed
    {
        $this->authorize('erp.production.manage');

        $validated = Validator::validate($request->all(), [
            'produced_qty' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $this->service->completeWorkOrder($id, (float) $validated['produced_qty'], (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'wo_completed');

        return redirect('/admin/erp/production/work-orders/' . $id);
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
