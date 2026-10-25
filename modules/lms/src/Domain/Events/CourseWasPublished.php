<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CourseWasPublished — دوره منتشر شد (ایندکس جست‌وجو/کش).
 */

namespace Modules\Lms\Domain\Events;

/**
 * @api 1.0
 */
final readonly class CourseWasPublished
{
    public function __construct(
        public string $courseId,
        public string $slug,
        public string $title,
        public int $instructorId,
        public int $priceMinor,
        public string $currency,
        public bool $isFree,
    ) {
    }

    public function eventName(): string
    {
        return 'lms.course.published';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'course_id' => $this->courseId,
            'slug' => $this->slug,
            'title' => $this->title,
            'instructor_id' => $this->instructorId,
            'price_minor' => $this->priceMinor,
            'currency' => $this->currency,
            'is_free' => $this->isFree,
        ];
    }
}
