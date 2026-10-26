<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DefinitionStatus — وضعیت تعریف فرایند در فرایندساز (DOC-098 §1.1).
 */

namespace Modules\Bpms\Domain\Enums;

/**
 * DefinitionStatus — draft (در حال طراحی) → published (قابل اجرا) →
 * retired (بازنشسته؛ نمونه‌های جاری با نسخهٔ خود ادامه می‌دهند).
 * نسخهٔ «فعال» per key با is_active مشخص می‌شود (uk_key_version).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
enum DefinitionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isRunnable(): bool
    {
        return $this === self::Published;
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Draft;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return \array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
