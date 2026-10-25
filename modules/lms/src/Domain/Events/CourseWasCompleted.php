<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CourseWasCompleted — همهٔ درس‌های دوره تمام شد (DOC-095 §3.2/§5).
 */

namespace Modules\Lms\Domain\Events;

/**
 * مصرف‌کننده: certificate workflow، notification.
 *
 * @api 1.0
 */
final readonly class CourseWasCompleted
{
    public function __construct(
        public string $enrollmentId,
        public int $studentId,
        public string $courseId,
        public string $courseTitle,
    ) {
    }

    public function eventName(): string
    {
        return 'lms.course.completed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'course_id' => $this->courseId,
            'course_title' => $this->courseTitle,
        ];
    }
}
