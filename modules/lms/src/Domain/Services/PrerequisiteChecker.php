<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * PrerequisiteChecker — بررسی پیش‌نیازهای دوره (DOC-095 §1.2).
 */

namespace Modules\Lms\Domain\Services;

/**
 * PrerequisiteChecker — تصمیم خالص روی مجموعهٔ دوره‌های «تکمیل‌شدهٔ» دانشجو.
 *
 * @api 1.0
 */
final class PrerequisiteChecker
{
    /**
     * @param list<string> $prerequisiteIds
     * @param list<string> $completedCourseIds
     *
     * @return list<string> پیش‌نیازهایِ هنوز تکمیل‌نشده (خالی = مجاز)
     */
    public function missing(array $prerequisiteIds, array $completedCourseIds): array
    {
        $completed = \array_flip($completedCourseIds);
        $missing = [];

        foreach ($prerequisiteIds as $pid) {
            if (!isset($completed[$pid])) {
                $missing[] = $pid;
            }
        }

        return $missing;
    }

    /**
     * @param list<string> $prerequisiteIds
     * @param list<string> $completedCourseIds
     */
    public function satisfied(array $prerequisiteIds, array $completedCourseIds): bool
    {
        return $this->missing($prerequisiteIds, $completedCourseIds) === [];
    }

    /**
     * تشخیص چرخه در گراف پیش‌نیاز (DFS) — حفاظ admin هنگام ویرایش.
     *
     * @param array<string, list<string>> $graph courseId → prerequisiteIds
     */
    public function hasCycle(array $graph): bool
    {
        $state = [];   // 0=unvisited 1=in-stack 2=done

        $visit = function (string $node) use (&$visit, &$state, $graph): bool {
            $state[$node] = 1;
            foreach ($graph[$node] ?? [] as $dep) {
                $depState = $state[$dep] ?? 0;
                if ($depState === 1) {
                    return true;
                }
                if ($depState === 0 && $visit($dep)) {
                    return true;
                }
            }
            $state[$node] = 2;

            return false;
        };

        foreach (\array_keys($graph) as $node) {
            if (($state[$node] ?? 0) === 0 && $visit($node)) {
                return true;
            }
        }

        return false;
    }
}
