<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * StudentWasEnrolled — ثبت‌نام قطعی دانشجو (DOC-095 §3.1/§5).
 */

namespace Modules\Lms\Domain\Events;

/**
 * مصرف‌کننده: notification (welcome)، report.
 *
 * @api 1.0
 */
final readonly class StudentWasEnrolled
{
    public function __construct(
        public string $enrollmentId,
        public int $studentId,
        public string $courseId,
        public string $courseTitle,
        public bool $paid,
        public int $priceMinor,
        public string $currency,
    ) {
    }

    public function eventName(): string
    {
        return 'lms.student.enrolled';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'course_id' => $this->courseId,
            'course_title' => $this->courseTitle,
            'paid' => $this->paid,
            'price_minor' => $this->priceMinor,
            'currency' => $this->currency,
        ];
    }
}
