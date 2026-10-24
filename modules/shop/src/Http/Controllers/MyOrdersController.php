<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * MyOrdersController — سفارش‌های مشتری (DOC-093 §7).
 */

namespace Modules\Shop\Http\Controllers;

use Modules\Shop\Application\UseCases\OrderWorkflowService;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class MyOrdersController
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderWorkflowService $workflow,
    ) {
    }

    /** فهرست سفارش‌های من — GET /shop/orders */
    public function index(RequestInterface $request): mixed
    {
        $userId = $this->currentUserId();
        $page = \max(1, (int) $request->input('page', 1));

        $result = $this->orders->paginate(['customer_id' => $userId], $page, 10);

        return View::render('shop::front.my-orders', [
            'orders' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 10,
        ]);
    }

    /** جزئیات سفارش — GET /shop/orders/{number} */
    public function show(string $number): mixed
    {
        $order = $this->orders->findByNumber($number);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.order_not_found');
        }

        $userId = $this->currentUserId();
        if ($order->customerId() !== $userId && !Gate::allows('view', $order)) {
            throw new ForbiddenException(messageId: 'shop::messages.order_forbidden');
        }

        return View::render('shop::front.order-show', [
            'order' => $order,
            'canCancel' => $order->customerId() === $userId && $order->isCancelableByCustomer(),
        ]);
    }

    /** لغو سفارش توسط مشتری — POST /shop/orders/{number}/cancel */
    public function cancel(string $number): mixed
    {
        $order = $this->orders->findByNumber($number);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.order_not_found');
        }

        $userId = $this->currentUserId();
        if ($order->customerId() !== $userId) {
            throw new ForbiddenException(messageId: 'shop::messages.order_forbidden');
        }
        if (!$order->isCancelableByCustomer()) {
            throw new ValidationException(
                errors: ['order' => ['این سفارش در این وضعیت قابل لغو نیست.']],
                messageId: 'shop::messages.order_not_cancelable',
            );
        }

        $this->workflow->cancel($order, 'لغو توسط مشتری');

        Session::flash('status', 'order_canceled');



        return redirect('/shop/orders/' . $order->orderNumber());
    }

    private function currentUserId(): int
    {
        $user = Auth::user();
        if ($user === null) {
            throw new ForbiddenException(messageId: 'shop::messages.login_required');
        }

        return (int) $user->getAuthIdentifier();
    }
}
