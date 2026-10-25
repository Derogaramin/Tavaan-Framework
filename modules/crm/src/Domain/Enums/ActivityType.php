<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ActivityType — نوع فعالیت (DOC-096 §1.2/§2).
 */

namespace Modules\Crm\Domain\Enums;

/**
 * @api 1.0
 */
enum ActivityType: string
{
    case Call = 'call';
    case Meeting = 'meeting';
    case Email = 'email';
    case Note = 'note';
    case Task = 'task';

    public static function tryFromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Note;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Call => 'تماس',
            self::Meeting => 'جلسه',
            self::Email => 'ایمیل',
            self::Note => 'یادداشت',
            self::Task => 'کار',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Call => '📞',
            self::Meeting => '🗓',
            self::Email => '✉️',
            self::Note => '📝',
            self::Task => '☑️',
        };
    }
}
