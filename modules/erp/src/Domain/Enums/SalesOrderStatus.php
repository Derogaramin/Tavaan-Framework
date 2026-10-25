<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * SalesOrderStatus — چرخهٔ سفارش فروش.
 */

namespace Modules\Erp\Domain\Enums;

/**
 * draft → confirmed → delivered → invoiced • canceled انشعاب.
 *
 * @api 1.0
 */
enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Delivered = 'delivered';
    case Invoiced = 'invoiced';
    case Canceled = 'canceled';

    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Draft => $target === self::Confirmed || $target === self::Canceled,
            self::Confirmed => $target === self::Delivered || $target === self::Canceled,
            self::Delivered => $target === self::Invoiced,
            self::Invoiced, self::Canceled => false,
        };
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Confirmed => 'قطعی‌شده',
            self::Delivered => 'تحویل‌شده',
            self::Invoiced => 'فاکتورشده',
            self::Canceled => 'لغوشده',
        };
    }
}
