<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * SalesController — فروش (/admin/erp/sales).
 */

namespace Modules\Erp\Http\Controllers\Admin;

use Modules\Erp\Application\UseCases\InventoryService;
use Modules\Erp\Application\UseCases\SalesService;
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
final class SalesController
{
    public function __construct(
        private readonly SalesService $service,
        private readonly InventoryService $inventory,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('erp.sales.create');

        $page = \max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $filters = [];
        if (($status = (string) $request->input('status', '')) !== '') {
            $filters['status'] = $status;
        }

        $customerNames = [];
        foreach ($this->service->customers() as $customer) {
            $customerNames[$customer->id()] = $customer->name();
        }

        return View::render('erp::admin.sales-index', [
            'orders' => $this->service->orders($filters, $perPage, ($page - 1) * $perPage),
            'total' => $this->service->countOrders($filters),
            'page' => $page,
            'perPage' => $perPage,
            'customerNames' => $customerNames,
            'customers' => $this->service->customers(),
            'invoices' => $this->service->invoices(),
            'statusFilter' => $status,
        ]);
    }

    public function create(): mixed
    {
        $this->authorize('erp.sales.create');

        $items = [];
        foreach ($this->inventory->items() as $item) {
            if ($item->isSold()) {
                $items[] = $item;
            }
        }

        return View::render('erp::admin.sales-form', [
            'customers' => $this->service->customers(),
            'warehouses' => $this->inventory->warehouses(),
            'items' => $items,
        ]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize('erp.sales.create');

        $validated = Validator::validate($request->all(), [
            'customer_id' => ['required', 'string'],
            'warehouse_id' => ['required', 'string'],
        ]);

        $itemIds = (array) $request->input('item_id', []);
        $qtys = (array) $request->input('qty', []);
        $prices = (array) $request->input('unit_price', []);

        $lines = [];
        foreach ($itemIds as $index => $itemId) {
            $qty = (float) ($qtys[$index] ?? 0);
            if (\trim((string) $itemId) !== '' && $qty > 0) {
                $lines[] = ['item_id' => \trim((string) $itemId), 'qty' => $qty, 'unit_price' => (int) ($prices[$index] ?? 0)];
            }
        }

        if ($lines === []) {
            Session::flash('error', 'دست‌کم یک قلم با مقدار مثبت لازم است.');

            return back();
        }

        try {
            $order = $this->service->createOrder((string) $validated['customer_id'], (string) $validated['warehouse_id'], $lines, (int) Auth::id());
            $this->service->confirmOrder($order->id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'so_confirmed');

        return redirect('/admin/erp/sales/' . $order->id());
    }

    public function show(string $id): mixed
    {
        $this->authorize('erp.sales.create');

        $order = $this->service->findOrder($id);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'erp::messages.not_found');
        }

        $itemNames = [];
        foreach ($this->inventory->items() as $item) {
            $itemNames[$item->id()] = $item->sku() . ' — ' . $item->name();
        }

        $invoices = [];
        foreach ($this->service->invoices($order->customerId()) as $invoice) {
            if ($invoice->salesOrderId() === $order->id()) {
                $invoices[] = $invoice;
            }
        }

        return View::render('erp::admin.sales-show', [
            'order' => $order,
            'itemNames' => $itemNames,
            'customer' => $this->service->findCustomer($order->customerId()),
            'invoices' => $invoices,
        ]);
    }

    public function confirm(string $id): mixed
    {
        $this->authorize('erp.sales.create');

        try {
            $this->service->confirmOrder($id);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'so_confirmed');

        return redirect('/admin/erp/sales/' . $id);
    }

    public function deliver(string $id): mixed
    {
        $this->authorize('erp.sales.deliver');

        try {
            $this->service->deliver($id, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'so_delivered');

        return redirect('/admin/erp/sales/' . $id);
    }

    public function invoice(string $id): mixed
    {
        $this->authorize('erp.sales.invoice');

        try {
            $this->service->issueInvoice($id, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'invoice_issued');

        return redirect('/admin/erp/sales/' . $id);
    }

    public function receivePayment(RequestInterface $request, string $id): mixed
    {
        $this->authorize('erp.payment.create');

        $validated = Validator::validate($request->all(), [
            'method' => ['required', 'in:cash,bank'],
            'amount' => ['nullable', 'integer', 'gt:0'],
        ]);

        try {
            $this->service->receivePayment(
                $id,
                (string) $validated['method'],
                isset($validated['amount']) ? (int) $validated['amount'] : null,
                (int) Auth::id(),
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'payment_in_recorded');

        return back();
    }

    public function storeCustomer(RequestInterface $request): mixed
    {
        $this->authorize('erp.customer.manage');

        $validated = Validator::validate($request->all(), [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        try {
            $this->service->createCustomer(
                (string) $validated['code'],
                (string) $validated['name'],
                isset($validated['phone']) ? (string) $validated['phone'] : null,
                isset($validated['email']) ? (string) $validated['email'] : null,
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'customer_created');

        return redirect('/admin/erp/sales');
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
