<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ContactStatus — وضعیت مخاطب (DOC-096 §2).
 */

namespace Modules\Crm\Domain\Enums;

/**
 * @api 1.0
 */
enum ContactStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    public static function tryFromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Active;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Inactive => 'غیرفعال',
            self::Blocked => 'مسدود',
        };
    }
}
