<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserStatusWasChanged — رویداد تغییر وضعیت حساب (active/inactive/banned).
 */

namespace Modules\CoreUser\Domain\Events;

/**
 * @api 1.0
 */
final readonly class UserStatusWasChanged
{
    public function __construct(
        public int $userId,
        public string $from,
        public string $to,
        public ?int $changedBy = null,
    ) {
    }
}
