<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CourseStatus — چرخهٔ وضعیت دوره (DOC-095 §2).
 */

namespace Modules\Lms\Domain\Enums;

/**
 * CourseStatus — draft → published → archived.
 *
 * @api 1.0
 */
enum CourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * ماتریس انتقال:
     * draft → published • published → archived|draft • archived → published.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Draft => $target === self::Published,
            self::Published => $target === self::Archived || $target === self::Draft,
            self::Archived => $target === self::Published,
        };
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Published => 'منتشرشده',
            self::Archived => 'آرشیوشده',
        };
    }
}
