<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * ContentStatus — چرخهٔ وضعیت انتشار محتوا (DOC-091 §2: draft|published|scheduled|archived).
 */

namespace Modules\Content\Domain\Enums;

/**
 * وضعیت انتشار یک قلم محتوا + انتقال‌های مجاز.
 *
 * @api 1.0
 * @see modules/content/README.md
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';

    /** آیا این وضعیت برای خوانندهٔ مهمان قابل مشاهده است؟ */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /**
     * آیا انتقال از این وضعیت به وضعیت مقصد مجاز است؟
     *
     * draft     → published | scheduled | archived
     * scheduled → published | draft | archived
     * published → archived | draft (برداشتن از انتشار)
     * archived  → draft (بازگردانی برای ویرایش)
     */
    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Draft => $target === self::Published || $target === self::Scheduled || $target === self::Archived,
            self::Scheduled => $target === self::Published || $target === self::Draft || $target === self::Archived,
            self::Published => $target === self::Archived || $target === self::Draft,
            self::Archived => $target === self::Draft,
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::Draft, self::Published, self::Scheduled, self::Archived];
    }

    /** برچسب فارسی (بدون وابستگی به Translator — برای دامنهٔ خالص). */
    public function labelFa(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Published => 'منتشرشده',
            self::Scheduled => 'زمان‌بندی‌شده',
            self::Archived => 'بایگانی‌شده',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Scheduled => 'Scheduled',
            self::Archived => 'Archived',
        };
    }
}
