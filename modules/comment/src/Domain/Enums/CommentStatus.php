<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentStatus — وضعیت‌های moderation (DOC-091 §2: pending|approved|spam).
 */

namespace Modules\Comment\Domain\Enums;

/**
 * @api 1.0
 * @see modules/comment/README.md
 */
enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Spam = 'spam';

    /** آیا برای نمایش عمومی مجاز است؟ */
    public function isVisible(): bool
    {
        return $this === self::Approved;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار تأیید',
            self::Approved => 'تأییدشده',
            self::Spam => 'اسپم',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Spam => 'Spam',
        };
    }
}
