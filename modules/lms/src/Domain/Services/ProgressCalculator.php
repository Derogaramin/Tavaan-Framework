<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * ProgressCalculator — درصد پیشرفت ثبت‌نام (DOC-095 §3.2).
 */

namespace Modules\Lms\Domain\Services;

/**
 * ProgressCalculator — تصمیم خالص:
 * percent = completed_lessons / total_lessons × 100 (گرد به پایین، ۲ رقم).
 * دوره بدون درس → ۰٪ (تکمیل خودکار بی‌معنی است).
 *
 * @api 1.0
 */
final class ProgressCalculator
{
    public function percent(int $completedLessons, int $totalLessons): float
    {
        if ($totalLessons <= 0) {
            return 0.0;
        }

        $completed = \max(0, \min($completedLessons, $totalLessons));

        return \round(\floor($completed * 10000 / $totalLessons) / 100, 2);
    }

    public function isCourseComplete(int $completedLessons, int $totalLessons): bool
    {
        return $totalLessons > 0 && $completedLessons >= $totalLessons;
    }
}
