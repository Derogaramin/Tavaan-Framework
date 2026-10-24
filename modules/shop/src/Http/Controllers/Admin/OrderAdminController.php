<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderAdminController — مدیریت سفارش‌ها + گزارش فروش (/admin/shop/orders).
 */

namespace Modules\Shop\Http\Controllers\Admin;

use Modules\Shop\Application\UseCases\OrderWorkflowService;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class OrderAdminController
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderWorkflowService $workflow,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('shop.order.view');

        $page = \max(1, (int) $request->input('page', 1));
        $result = $this->orders->paginate([
            'status' => (string) $request->input('status', ''),
            'search' => (string) $request->input('q', ''),
        ], $page, 20);

        return View::render('shop::admin.orders-index', [
            'orders' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'statusFilter' => (string) $request->input('status', ''),
            'query' => (string) $request->input('q', ''),
            'stats' => $this->orders->salesStats(),
        ]);
    }

    public function show(RequestInterface $request, string $id): mixed
    {
        $this->authorize('shop.order.view');

        $order = $this->findOr404($id);

        return View::render('shop::admin.order-show', [
            'order' => $order,
            'movements' => $this->stockMovements($order->id()),
        ]);
    }

    /** انتقال وضعیت: ship/deliver/cancel — POST /admin/shop/orders/{id}/status */
    public function updateStatus(RequestInterface $request, string $id): mixed
    {
        $this->authorize('shop.order.update');
        $order = $this->findOr404($id);

        $validated = Validator::validate($request->all(), [
            'action' => ['required', 'in:ship,deliver,cancel'],
            'tracking_code' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], attributes: ['action' => 'عملیات']);

        try {
            match ((string) $validated['action']) {
                'ship' => $this->workflow->ship($order, (string) ($validated['tracking_code'] ?? '')),
                'deliver' => $this->workflow->deliver($order),
                'cancel' => $this->workflow->cancel($order, (string) ($validated['reason'] ?? 'لغو توسط مدیر')),
            };
        } catch (\DomainException | ValidationException $e) {
            Session::flash('error', $e->getMessage());


            return back();
        }

        Session::flash('status', 'order_status_updated');



        return back();
    }

    /** استرداد — POST /admin/shop/orders/{id}/refund */
    public function refund(RequestInterface $request, string $id): mixed
    {
        $this->authorize('shop.order.refund');
        $order = $this->findOr404($id);

        if (!$order->isPaid()) {
            Session::flash('error', 'سفارش پرداخت‌شده نیست.');


            return back();
        }

        try {
            $this->workflow->refund($order, (string) $request->input('reason', 'استرداد مدیریتی'));
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());


            return back();
        }

        Session::flash('status', 'order_refunded');



        return back();
    }

    public function destroy(string $id): mixed
    {
        $this->authorize('shop.order.delete');
        $order = $this->findOr404($id);
        $this->orders->delete($order->id());

        Session::flash('status', 'order_deleted');



        return redirect('/admin/shop/orders');
    }

    /** خروجی CSV گزارش فروش (AC-SHOP-05 — read model ساده). */
    public function salesCsv(RequestInterface $request): mixed
    {
        $this->authorize('shop.report.view');

        $result = $this->orders->paginate([
            'status' => (string) $request->input('status', ''),
        ], 1, 100);

        $lines = ["order_number,status,items,total,currency,placed_at,paid_at"];
        foreach ($result['rows'] as $order) {
            $lines[] = \sprintf(
                '%s,%s,%d,%d,%s,%s,%s',
                $order->orderNumber(),
                $order->status()->value,
                $order->itemCount(),
                $order->total()->minor,
                $order->currency(),
                $order->placedAt()?->format('Y-m-d H:i:s') ?? '',
                $order->paidAt()?->format('Y-m-d H:i:s') ?? '',
            );
        }

        /** @var object $responses */
        $responses = \app()->make('response.factory');

        return $responses->make(
            "\xEF\xBB\xBF" . \implode("\n", $lines),
            200,
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="shop-sales.csv"'],
        );
    }

    /** @return list<array<string, mixed>> */
    private function stockMovements(string $orderId): array
    {
        try {
            return \Tavaan\Core\Facade\Facades\DB::table('shop_stock_movements')
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->get();
        } catch (\Throwable) {
            return [];
        }
    }

    private function findOr404(string $id): \Modules\Shop\Domain\Entities\Order
    {
        $order = $this->orders->findById($id);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.order_not_found');
        }

        return $order;
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'shop::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}
