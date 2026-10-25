<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * WorkOrderStatus — چرخهٔ سفارش تولید (DOC-097 §2.4).
 */

namespace Modules\Erp\Domain\Enums;

/**
 * @api 1.0
 */
enum WorkOrderStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Planned => $target === self::InProgress || $target === self::Canceled,
            self::InProgress => $target === self::Completed || $target === self::Canceled,
            self::Completed, self::Canceled => false,
        };
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Planned => 'برنامه‌ریزی‌شده',
            self::InProgress => 'در حال تولید',
            self::Completed => 'تکمیل‌شده',
            self::Canceled => 'لغوشده',
        };
    }
}
