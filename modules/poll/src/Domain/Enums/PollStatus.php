<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollStatus — چرخهٔ وضعیت نظرسنجی (draft|open|closed).
 */

namespace Modules\Poll\Domain\Enums;

/**
 * وضعیت نظرسنجی + انتقال‌های مجاز.
 *
 * draft  → open | closed
 * open   → closed
 * closed → open (بازگشایی)
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
enum PollStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';

    /** آیا برای بازدیدکنندهٔ عمومی قابل مشاهده است؟ */
    public function isPublic(): bool
    {
        return $this === self::Open || $this === self::Closed;
    }

    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Draft => $target === self::Open || $target === self::Closed,
            self::Open => $target === self::Closed,
            self::Closed => $target === self::Open,
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::Draft, self::Open, self::Closed];
    }

    /** برچسب فارسی (بدون وابستگی به Translator — برای دامنهٔ خالص). */
    public function labelFa(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Open => 'باز',
            self::Closed => 'بسته',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }
}
