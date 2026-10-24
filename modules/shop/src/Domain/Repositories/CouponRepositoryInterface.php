<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CouponRepositoryInterface — پورت ماندگاری کوپن‌ها.
 */

namespace Modules\Shop\Domain\Repositories;

use Modules\Shop\Domain\Entities\Coupon;

/**
 * @api 1.0
 */
interface CouponRepositoryInterface
{
    public function save(Coupon $coupon): int;

    public function findById(int $id): ?Coupon;

    /** کد نرمال‌شده (upper) — فقط فعال‌ها؟ خیر: برای پیام خطای دقیق‌تر همه. */
    public function findByCode(string $code): ?Coupon;

    public function findActiveByCode(string $code): ?Coupon;

    public function codeExists(string $code, ?int $exceptId = null): bool;

    /** @return list<Coupon> */
    public function all(int $limit = 200): array;

    public function incrementUsage(Coupon $coupon): void;

    public function delete(int $id): void;
}
