<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * LeadStatus — چرخهٔ سرنخ (DOC-096 §2/§3.1).
 */

namespace Modules\Crm\Domain\Enums;

/**
 * LeadStatus — new → qualified → converted، با انشعاب lost.
 *
 * @api 1.0
 */
enum LeadStatus: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Converted = 'converted';
    case Lost = 'lost';

    /**
     * ماتریس انتقال:
     * new       → qualified | lost
     * qualified → converted | lost | new (رد صلاحیت)
     * converted/lost → (پایانی)
     */
    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::New => $target === self::Qualified || $target === self::Lost,
            self::Qualified => $target === self::Converted || $target === self::Lost || $target === self::New,
            self::Converted, self::Lost => false,
        };
    }

    public function isOpen(): bool
    {
        return $this === self::New || $this === self::Qualified;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::New => 'جدید',
            self::Qualified => 'واجد صلاحیت',
            self::Converted => 'تبدیل‌شده',
            self::Lost => 'ازدست‌رفته',
        };
    }
}
