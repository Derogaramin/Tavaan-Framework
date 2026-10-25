<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CourseWasUpdated — ویرایش دوره (re-index جست‌وجو + invalidate کش).
 */

namespace Modules\Lms\Domain\Events;

/**
 * @api 1.0
 */
final readonly class CourseWasUpdated
{
    public function __construct(
        public string $courseId,
        public string $slug,
        public string $title,
        public string $status,
    ) {
    }

    public function eventName(): string
    {
        return 'lms.course.updated';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'course_id' => $this->courseId,
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status,
        ];
    }
}
