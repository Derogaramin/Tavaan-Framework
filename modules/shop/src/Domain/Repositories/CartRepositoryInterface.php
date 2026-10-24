<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CartRepositoryInterface — پورت ماندگاری سبد.
 */

namespace Modules\Shop\Domain\Repositories;

use Modules\Shop\Domain\Entities\Cart;

/**
 * @api 1.0
 */
interface CartRepositoryInterface
{
    /** سبد فعال کاربر/مهمان — در نبود، ساخت جدید (بدون persist تا اولین تغییر). */
    public function findActive(?int $userId, ?string $sessionId): ?Cart;

    /** یافتن + ساخت + persist. */
    public function findOrCreate(?int $userId, ?string $sessionId): Cart;

    /** ذخیرهٔ سبد + اقلام (جایگزینی اقلام). */
    public function save(Cart $cart): void;

    /** انتقال سبد مهمان به کاربر پس از ورود (merge). */
    public function assignToUser(string $sessionId, int $userId): ?Cart;

    public function clear(Cart $cart): void;
}
