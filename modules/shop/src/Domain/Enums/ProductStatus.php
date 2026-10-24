<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ProductStatus — وضعیت محصول (DOC-093 §3.1: draft|active|archived).
 */

namespace Modules\Shop\Domain\Enums;

/**
 * @api 1.0
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function isPublic(): bool
    {
        return $this === self::Active;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Active => 'فعال',
            self::Archived => 'بایگانی‌شده',
        };
    }
}
