<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderPolicy — مالکیت مشتری + مجوزهای مدیریتی سفارش (DOC-093 §5).
 */

namespace Modules\Shop\Policies;

use Modules\Shop\Domain\Entities\Order;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * OrderPolicy:
 * - customer: مشاهده/لغو سفارش **خود** (تا پیش از ارسال)
 * - operator: مشاهدهٔ همه
 * - manager: تغییر وضعیت + استرداد
 *
 * @api 1.0
 */
final class OrderPolicy
{
    public function view(Authenticatable $user, Order $order): bool
    {
        return $this->isOwner($user, $order) || Gate::allows('shop.order.view');
    }

    public function update(Authenticatable $user, Order $order): bool
    {
        return Gate::allows('shop.order.update');
    }

    public function cancel(Authenticatable $user, Order $order): bool
    {
        if ($this->isOwner($user, $order)) {
            return $order->isCancelableByCustomer();
        }

        return Gate::allows('shop.order.update');
    }

    public function refund(Authenticatable $user, Order $order): bool
    {
        return Gate::allows('shop.order.refund');
    }

    public function delete(Authenticatable $user, Order $order): bool
    {
        return Gate::allows('shop.order.delete');
    }

    private function isOwner(Authenticatable $user, Order $order): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $order->customerId();
    }
}
