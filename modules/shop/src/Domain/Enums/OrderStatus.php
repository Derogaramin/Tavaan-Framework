<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderStatus — چرخهٔ وضعیت سفارش (DOC-093 §4.2-4.3).
 */

namespace Modules\Shop\Domain\Enums;

/**
 * OrderStatus — pending → paid → shipped → delivered، با انشعاب
 * canceled (از pending/paid) و refunded (از paid/shipped/delivered).
 *
 * @api 1.0
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    /** پایانی — هیچ انتقالی بیرون نمی‌رود (جز refund پس از delivered که مدیرتی است). */
    public function isFinal(): bool
    {
        return $this === self::Canceled || $this === self::Refunded || $this === self::Delivered;
    }

    /**
     * ماتریس انتقال.
     *
     * pending   → paid | canceled
     * paid      → shipped | canceled | refunded
     * shipped   → delivered | refunded
     * delivered → refunded (استرداد مدیرتی)
     * canceled/refunded → (پایانی)
     */
    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Pending => $target === self::Paid || $target === self::Canceled,
            self::Paid => $target === self::Shipped || $target === self::Canceled || $target === self::Refunded,
            self::Shipped => $target === self::Delivered || $target === self::Refunded,
            self::Delivered => $target === self::Refunded,
            self::Canceled, self::Refunded => false,
        };
    }

    /** آیا لغو، آزادسازی موجودی لازم دارد؟ */
    public function holdsStock(): bool
    {
        return $this === self::Pending || $this === self::Paid || $this === self::Shipped;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار پرداخت',
            self::Paid => 'پرداخت‌شده',
            self::Shipped => 'ارسال‌شده',
            self::Delivered => 'تحویل‌شده',
            self::Canceled => 'لغوشده',
            self::Refunded => 'مسترد',
        };
    }
}
