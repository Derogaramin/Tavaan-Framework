<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * FlagStatus — وضعیت گزارش در صف moderation (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Enums;

/**
 * FlagStatus — pending (در صف) → resolved (اقدام شد، معمولاً hide)
 * یا dismissed (رد گزارش).
 *
 * @api 1.0
 */
enum FlagStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    public static function tryFromOrDefault(string $value): self
    {
        return self::tryFrom($value) ?? self::Pending;
    }
}
