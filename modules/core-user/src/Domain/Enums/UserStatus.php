<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserStatus — وضعیت حساب کاربری.
 */

namespace Modules\CoreUser\Domain\Enums;

/**
 * @api 1.0
 * @see modules/core-user/README.md
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';

    public function canLogin(): bool
    {
        return $this === self::Active;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Inactive => 'غیرفعال',
            self::Banned => 'مسدود',
        };
    }
}
