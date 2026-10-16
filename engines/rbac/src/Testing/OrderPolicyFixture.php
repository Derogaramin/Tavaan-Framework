<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac\Testing;

use Tavaan\Core\Contracts\Auth\Authenticatable;

/**
 * Fixture تست RBAC — Policy سفارش (DOC-057 §4).
 *
 * @api internal
 */
final class OrderPolicyFixture
{
    public function view(Authenticatable $user, OrderFixture $order): bool
    {
        return (int) $order->getAttribute('user_id') === (int) $user->getAuthIdentifier()
            || $this->can($user, 'shop.order.view');
    }

    public function update(Authenticatable $user, OrderFixture $order): bool
    {
        return $this->can($user, 'shop.order.update');
    }

    public function refund(Authenticatable $user, OrderFixture $order): bool
    {
        return $this->can($user, 'shop.order.refund') && $order->isRefundable();
    }

    public function delete(Authenticatable $user, OrderFixture $order): bool
    {
        return \method_exists($user, 'isSuperAdmin') && (bool) $user->isSuperAdmin();
    }

    private function can(Authenticatable $user, string $ability): bool
    {
        if (\method_exists($user, 'can')) {
            return (bool) $user->can($ability);
        }

        return false;
    }
}
