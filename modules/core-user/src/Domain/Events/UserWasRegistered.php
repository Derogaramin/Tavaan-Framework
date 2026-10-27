<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserWasRegistered — رویداد ثبت‌نام عمومی کاربر.
 */

namespace Modules\CoreUser\Domain\Events;

/**
 * پس از ثبت‌نام موفق (فرم /register یا API) منتشر می‌شود.
 *
 * @api 1.0
 */
final readonly class UserWasRegistered
{
    public function __construct(
        public int $userId,
        public string $email,
        public string $name,
        public string $defaultRole = '',
    ) {
    }
}
