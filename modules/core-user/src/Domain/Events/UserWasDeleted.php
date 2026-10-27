<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserWasDeleted — رویداد حذف کاربر از پنل مدیریت.
 */

namespace Modules\CoreUser\Domain\Events;

/**
 * @api 1.0
 */
final readonly class UserWasDeleted
{
    public function __construct(
        public int $userId,
        public string $email,
        public ?int $deletedBy = null,
    ) {
    }
}
