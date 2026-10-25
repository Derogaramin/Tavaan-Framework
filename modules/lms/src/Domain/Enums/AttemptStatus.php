<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * AttemptStatus — چرخهٔ تلاش آزمون (DOC-095 §3.3).
 */

namespace Modules\Lms\Domain\Enums;

/**
 * in_progress → submitted → graded.
 * submitted = پاسخ‌ها ثبت و خودکارها نمره گرفته‌اند؛ اگر سؤال دستی ندارد
 * همان لحظه graded می‌شود (added: ستون status — DOC فقط answers/score/passed دارد).
 *
 * @api 1.0
 */
enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function labelFa(): string
    {
        return match ($this) {
            self::InProgress => 'در جریان',
            self::Submitted => 'در انتظار نمره‌دهی',
            self::Graded => 'نمره‌دهی‌شده',
        };
    }
}
