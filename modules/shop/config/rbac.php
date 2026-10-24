<?php

declare(strict_types=1);

/*
 * ماژول فروشگاه — نگاشت Policyهای RBAC (ادغام در rbac.policies).
 */

return [
    'policies' => [
        \Modules\Shop\Domain\Entities\Product::class => \Modules\Shop\Policies\ProductPolicy::class,
        \Modules\Shop\Domain\Entities\Order::class => \Modules\Shop\Policies\OrderPolicy::class,
    ],
];
