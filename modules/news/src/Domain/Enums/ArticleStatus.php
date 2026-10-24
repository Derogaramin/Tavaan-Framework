<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleStatus — چرخهٔ workflow انتشار خبر (DOC-092 §2-3).
 */

namespace Modules\News\Domain\Enums;

/**
 * ArticleStatus — draft|review|approved|published|archived.
 *
 * جریان (DOC-092 §3):
 * ```
 * draft → review (نویسنده submit) → approved (ویراستار) → published (سردبیر)
 *   ↑            │                        │
 *   └── reject ←─┘        scheduled_at → انتشار خودکار
 * ```
 *
 * @api 1.0
 * @see modules/news/README.md
 */
enum ArticleStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';

    /** آیا برای خوانندهٔ عمومی قابل مشاهده است؟ */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /** در چرخهٔ تولید است (پیش از انتشار)؟ */
    public function isInPipeline(): bool
    {
        return $this === self::Draft || $this === self::Review || $this === self::Approved;
    }

    /**
     * ماتریس انتقال workflow.
     *
     * draft     → review (submit) | archived
     * review    → approved | draft (reject)
     * approved  → published | draft (برگشت به نویسنده) | archived
     * published → archived | draft (unpublish)
     * archived  → draft
     */
    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Draft => $target === self::Review || $target === self::Archived,
            self::Review => $target === self::Approved || $target === self::Draft,
            self::Approved => $target === self::Published || $target === self::Draft || $target === self::Archived,
            self::Published => $target === self::Archived || $target === self::Draft,
            self::Archived => $target === self::Draft,
        };
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Review => 'در بازبینی',
            self::Approved => 'تأییدشده',
            self::Published => 'منتشرشده',
            self::Archived => 'بایگانی‌شده',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Review => 'In review',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}
