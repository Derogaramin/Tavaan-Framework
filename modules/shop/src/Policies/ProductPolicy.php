<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ProductPolicy — مجوزهای مدیریت محصول (DOC-093 §5).
 */

namespace Modules\Shop\Policies;

use Modules\Shop\Domain\Entities\Product;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * @api 1.0
 */
final class ProductPolicy
{
    public function view(Authenticatable $user, Product $product): bool
    {
        return Gate::allows('shop.product.view');
    }

    public function update(Authenticatable $user, Product $product): bool
    {
        return Gate::allows('shop.product.update');
    }

    public function delete(Authenticatable $user, Product $product): bool
    {
        return Gate::allows('shop.product.delete');
    }

    /** مدیریت موجودی (inventory-keeper). */
    public function manageInventory(Authenticatable $user, Product $product): bool
    {
        return Gate::allows('shop.inventory.manage');
    }
}
