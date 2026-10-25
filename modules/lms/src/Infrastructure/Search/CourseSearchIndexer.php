<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CourseSearchIndexer — ایندکس جست‌وجوی فارسی دوره‌ها (DOC-095 §meta: search).
 */

namespace Modules\Lms\Infrastructure\Search;

use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Tavaan\Core\Facade\Facades\Search;

/**
 * CourseSearchIndexer — شنوندهٔ رویدادهای course.
 *
 * ایندکس `lms_courses` (عنوان + خلاصه + نام مدرس). فقط دوره‌های
 * منتشرشده ایندکس می‌شوند؛ draft/archived → حذف از ایندکس.
 * best-effort: خطای ایندکس جریان اصلی را نمی‌شکند.
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class CourseSearchIndexer
{
    /**
     * @param array<string, mixed> $config کلید config('lms.search')
     */
    public function __construct(
        private readonly CourseRepositoryInterface $courses,
        private readonly array $config = [],
    ) {
    }

    public function onCoursePublished(string $event, mixed $payload): void
    {
        $this->indexCourse((string) ((array) $payload)['course_id'] ?? '');
    }

    public function onCourseUpdated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $id = (string) ($payload['course_id'] ?? '');
        $status = (string) ($payload['status'] ?? '');

        if ($status !== 'published') {
            $this->removeCourse($id);

            return;
        }

        $this->indexCourse($id);
    }

    public function onCourseDeleted(string $courseId): void
    {
        $this->removeCourse($courseId);
    }

    /** ایندکس کامل یک دوره (reindex دستی/seed). */
    public function indexCourse(string $courseId): void
    {
        if (!$this->enabled() || $courseId === '') {
            return;
        }

        try {
            $course = $this->courses->findById($courseId);
            if ($course === null) {
                return;
            }
            if (!$course->isPublished()) {
                $this->removeCourse($courseId);

                return;
            }

            Search::index($this->index(), [
                'id' => $course->id(),
                'title' => $course->title(),
                'slug' => $course->slug(),
                'summary' => \strip_tags((string) $course->summary()),
                'level' => $course->level()->value,
                'is_free' => $course->isFree(),
                'student_count' => $course->studentCount(),
                'published_at' => $course->publishedAt()?->format('Y-m-d H:i:s'),
            ], $course->id());
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function removeCourse(string $courseId): void
    {
        if (!$this->enabled() || $courseId === '') {
            return;
        }

        try {
            Search::delete($this->index(), $courseId);
        } catch (\Throwable) {
            // best-effort — سند ممکن است وجود نداشته باشد
        }
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    private function index(): string
    {
        return (string) ($this->config['index'] ?? 'lms_courses');
    }
}
