<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * ProgressStatus — وضعیت پیشرفت درس (DOC-095 §2/§3.2).
 */

namespace Modules\Lms\Domain\Enums;

/**
 * @api 1.0
 */
enum ProgressStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function labelFa(): string
    {
        return match ($this) {
            self::NotStarted => 'شروع نشده',
            self::InProgress => 'در حال یادگیری',
            self::Completed => 'تکمیل شده',
        };
    }
}
