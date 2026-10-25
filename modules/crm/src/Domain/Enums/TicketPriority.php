<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketPriority — اولویت تیکت (DOC-096 §2/§3.3).
 */

namespace Modules\Crm\Domain\Enums;

/**
 * @api 1.0
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public static function tryFromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Normal;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Low => 'کم',
            self::Normal => 'عادی',
            self::High => 'زیاد',
            self::Urgent => 'فوری',
        };
    }
}
