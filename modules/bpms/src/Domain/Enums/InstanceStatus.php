<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * InstanceStatus — وضعیت نمونهٔ فرایند (DOC-098 §2).
 */

namespace Modules\Bpms\Domain\Enums;

/**
 * InstanceStatus — چرخهٔ حیات نمونه: running → completed|terminated با
 * امکان suspend/resume موقت (مدیریتی).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
enum InstanceStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Terminated;
    }

    /** آیا توکن‌ها می‌توانند حرکت کنند؟ */
    public function isActive(): bool
    {
        return $this === self::Running;
    }

    /** ساخت از رشته — ناشناخته → Running (ستون DB محدود به مقادیر معتبر است). */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Running;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return \array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
