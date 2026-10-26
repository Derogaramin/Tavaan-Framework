<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskStatus — وضعیت کار انسانی (DOC-098 §2).
 */

namespace Modules\Bpms\Domain\Enums;

/**
 * TaskStatus — pending (در صف/قابل تصاحب) → claimed (تصاحب‌شده) →
 * completed؛ canceled وقتی نمونه terminate می‌شود.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
enum TaskStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Claimed;
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Canceled;
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Pending;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return \array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
