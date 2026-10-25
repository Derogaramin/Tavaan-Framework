<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ProcurementController — تدارکات (/admin/erp/purchases).
 */

namespace Modules\Erp\Http\Controllers\Admin;

use Modules\Erp\Application\UseCases\InventoryService;
use Modules\Erp\Application\UseCases\ProcurementService;
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
final class ProcurementController
{
    public function __construct(
        private readonly ProcurementService $service,
        private readonly InventoryService $inventory,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('erp.purchase.create', own: 'erp.purchase.approve-finance');

        $page = \max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $filters = [];
        if (($status = (string) $request->input('status', '')) !== '') {
            $filters['status'] = $status;
        }

        $supplierNames = [];
        foreach ($this->service->suppliers() as $supplier) {
            $supplierNames[$supplier->id()] = $supplier->name();
        }

        return View::render('erp::admin.purchases-index', [
            'orders' => $this->service->purchaseOrders($filters, $perPage, ($page - 1) * $perPage),
            'total' => $this->service->countPurchaseOrders($filters),
            'page' => $page,
            'perPage' => $perPage,
            'supplierNames' => $supplierNames,
            'suppliers' => $this->service->suppliers(),
            'invoices' => $this->service->supplierInvoices(),
            'router' => $this->service->approvalRouter(),
            'statusFilter' => $status,
        ]);
    }

    public function create(): mixed
    {
        $this->authorize('erp.purchase.create');

        return View::render('erp::admin.purchase-form', [
            'suppliers' => $this->service->suppliers(),
            'items' => $this->inventory->items(),
            'router' => $this->service->approvalRouter(),
        ]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize('erp.purchase.create');

        $validated = Validator::validate($request->all(), [
            'supplier_id' => ['required', 'string'],
        ]);

        $itemIds = (array) $request->input('item_id', []);
        $qtys = (array) $request->input('qty', []);
        $prices = (array) $request->input('unit_price', []);

        $lines = [];
        foreach ($itemIds as $index => $itemId) {
            $itemId = \trim((string) $itemId);
            $qty = (float) ($qtys[$index] ?? 0);
            if ($itemId === '' || $qty <= 0) {
                continue;
            }
            $lines[] = ['item_id' => $itemId, 'qty' => $qty, 'unit_price' => (int) ($prices[$index] ?? 0)];
        }

        if ($lines === []) {
            Session::flash('error', 'دست‌کم یک قلم با مقدار مثبت لازم است.');

            return back();
        }

        try {
            $order = $this->service->createPurchaseOrder((string) $validated['supplier_id'], $lines, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'po_created');

        return redirect('/admin/erp/purchases/' . $order->id());
    }

    public function show(string $id): mixed
    {
        $this->authorize('erp.purchase.create', own: 'erp.purchase.approve-finance');

        $order = $this->service->findPurchaseOrder($id);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'erp::messages.not_found');
        }

        $itemNames = [];
        foreach ($this->inventory->items() as $item) {
            $itemNames[$item->id()] = $item->sku() . ' — ' . $item->name();
        }

        $supplier = $this->service->findSupplier($order->supplierId());
        $invoices = $this->service->supplierInvoices($order->supplierId());
        $receipts = [];
        foreach ($this->service->goodsReceipts(100) as $receipt) {
            if ($receipt->poId() === $order->id()) {
                $receipts[] = $receipt;
            }
        }

        return View::render('erp::admin.purchase-show', [
            'order' => $order,
            'itemNames' => $itemNames,
            'supplier' => $supplier,
            'invoices' => $invoices,
            'receipts' => $receipts,
            'warehouses' => $this->inventory->warehouses(),
            'router' => $this->service->approvalRouter(),
        ]);
    }

    public function submit(string $id): mixed
    {
        $this->authorize('erp.purchase.create');

        return $this->run(function () use ($id): string {
            $this->service->submitForApproval($id, (int) Auth::id());

            return 'po_submitted';
        }, $id);
    }

    public function approveFinance(string $id): mixed
    {
        $this->authorize('erp.purchase.approve-finance');

        return $this->run(function () use ($id): string {
            $this->service->approveByFinance($id, (int) Auth::id());

            return 'po_approved_finance';
        }, $id);
    }

    public function approveCeo(string $id): mixed
    {
        $this->authorize('erp.purchase.approve-ceo');

        return $this->run(function () use ($id): string {
            $this->service->approveByCeo($id, (int) Auth::id());

            return 'po_approved_ceo';
        }, $id);
    }

    public function reject(RequestInterface $request, string $id): mixed
    {
        $this->authorize('erp.purchase.approve-finance', own: 'erp.purchase.approve-ceo');

        $reason = \trim((string) $request->input('reason', ''));

        try {
            $this->service->reject($id, (int) Auth::id(), $reason !== '' ? $reason : 'بدون دلیل');
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'po_rejected');

        return redirect('/admin/erp/purchases/' . $id);
    }

    /** ثبت رسید برای سفارش (ردیف‌ها: item_id[]/qty[]). */
    public function receive(RequestInterface $request, string $id): mixed
    {
        $this->authorize('erp.purchase.receive');

        $warehouseId = (string) $request->input('warehouse_id', '');
        $itemIds = (array) $request->input('item_id', []);
        $qtys = (array) $request->input('qty', []);

        $lines = [];
        foreach ($itemIds as $index => $itemId) {
            $qty = (float) ($qtys[$index] ?? 0);
            if ((string) $itemId !== '' && $qty > 0) {
                $lines[] = ['item_id' => \trim((string) $itemId), 'qty' => $qty];
            }
        }

        if ($warehouseId === '' || $lines === []) {
            Session::flash('error', 'انبار و دست‌کم یک ردیف رسید لازم است.');

            return back();
        }

        try {
            $this->service->registerReceipt($id, $warehouseId, $lines, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'receipt_registered');

        return redirect('/admin/erp/purchases/' . $id);
    }

    public function storeSupplier(RequestInterface $request): mixed
    {
        $this->authorize('erp.supplier.manage');

        $validated = Validator::validate($request->all(), [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        try {
            $this->service->createSupplier(
                (string) $validated['code'],
                (string) $validated['name'],
                isset($validated['phone']) ? (string) $validated['phone'] : null,
                isset($validated['email']) ? (string) $validated['email'] : null,
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'supplier_created');

        return redirect('/admin/erp/purchases');
    }

    public function storeSupplierInvoice(RequestInterface $request): mixed
    {
        $this->authorize('erp.purchase.invoice');

        $validated = Validator::validate($request->all(), [
            'supplier_id' => ['required', 'string'],
            'amount' => ['required', 'integer', 'gt:0'],
            'po_id' => ['nullable', 'string'],
        ]);

        try {
            $this->service->recordSupplierInvoice(
                (string) $validated['supplier_id'],
                (int) $validated['amount'],
                isset($validated['po_id']) && $validated['po_id'] !== '' ? (string) $validated['po_id'] : null,
                null,
                (int) Auth::id(),
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'supplier_invoice_recorded');

        return back();
    }

    public function paySupplierInvoice(RequestInterface $request, string $id): mixed
    {
        $this->authorize('erp.payment.create');

        $method = (string) $request->input('method', 'bank');

        try {
            $this->service->paySupplierInvoice($id, \in_array($method, ['cash', 'bank'], true) ? $method : 'bank', (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'payment_out_recorded');

        return back();
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    private function run(callable $action, string $id): mixed
    {
        try {
            $flash = $action();
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', \is_string($flash) ? $flash : 'po_submitted');

        return redirect('/admin/erp/purchases/' . $id);
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
