<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * LessonWasCompleted — درس تکمیل شد (DOC-095 §3.2/§5).
 */

namespace Modules\Lms\Domain\Events;

/**
 * مصرف‌کننده: progress recalc، gamification.
 *
 * @api 1.0
 */
final readonly class LessonWasCompleted
{
    public function __construct(
        public string $enrollmentId,
        public int $studentId,
        public string $courseId,
        public string $lessonId,
        public string $lessonTitle,
        public float $progressPercent,
    ) {
    }

    public function eventName(): string
    {
        return 'lms.lesson.completed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'course_id' => $this->courseId,
            'lesson_id' => $this->lessonId,
            'lesson_title' => $this->lessonTitle,
            'progress_percent' => $this->progressPercent,
        ];
    }
}
