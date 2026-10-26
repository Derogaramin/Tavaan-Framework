<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * FieldType — نوع فیلدهای فرم‌ساز پویا (DOC-098 §4).
 */

namespace Modules\Bpms\Domain\Enums;

/**
 * FieldType — انواع فیلد پشتیبانی‌شده در form_schema. هر نوع به قاعدهٔ
 * validation متناظر (موتور validation) و widget رندر متناظر نگاشت می‌شود.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case JalaliDate = 'jalali_date';
    case Date = 'date';
    case Email = 'email';
    case Mobile = 'mobile';
    case Hidden = 'hidden';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Text;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return \array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
