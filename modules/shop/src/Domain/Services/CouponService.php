<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CouponService — اعتبارسنجی و محاسبهٔ تخفیف کوپن (DOC-093 §8).
 */

namespace Modules\Shop\Domain\Services;

use Modules\Shop\Domain\Entities\Coupon;
use Modules\Shop\Domain\Repositories\CouponRepositoryInterface;
use Modules\Shop\Domain\ValueObjects\Money;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class CouponService
{
    public function __construct(private readonly CouponRepositoryInterface $coupons)
    {
    }

    /**
     * اعتبارسنجی کد کوپن برای این subtotal.
     *
     * @return array{coupon: Coupon, discount: Money}|array{error: string}
     */
    public function validate(string $code, Money $subtotal): array
    {
        $code = \mb_strtoupper(\trim($code));
        if ($code === '') {
            return ['error' => 'coupon_empty'];
        }

        $coupon = $this->coupons->findActiveByCode($code);
        if ($coupon === null) {
            return ['error' => 'coupon_invalid'];
        }

        if (!$coupon->isValidFor($subtotal)) {
            if ($coupon->usageLimit() !== null && $coupon->usedCount() >= $coupon->usageLimit()) {
                return ['error' => 'coupon_exhausted'];
            }
            if ($coupon->expiresAt() !== null && $coupon->expiresAt() < new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
                return ['error' => 'coupon_expired'];
            }

            return ['error' => 'coupon_min_cart'];
        }

        return ['coupon' => $coupon, 'discount' => $coupon->discountFor($subtotal)];
    }

    /** ثبت مصرف کوپن (پس از ثبت سفارش موفق). */
    public function consume(Coupon $coupon): void
    {
        $this->coupons->incrementUsage($coupon);
    }
}
