<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskPriority — اولویت کار (DOC-098 §2).
 */

namespace Modules\Bpms\Domain\Enums;

/**
 * TaskPriority — اولویت نمایش/صف‌بندی کارها در inbox.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /** وزن عددی برای مرتب‌سازی. */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Normal => 1,
            self::High => 2,
            self::Urgent => 3,
        };
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Normal;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return \array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
