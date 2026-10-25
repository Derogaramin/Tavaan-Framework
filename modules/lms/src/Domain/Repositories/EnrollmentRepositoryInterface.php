<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * EnrollmentRepositoryInterface — پورت مخزن ثبت‌نام + پیشرفت درس.
 */

namespace Modules\Lms\Domain\Repositories;

use Modules\Lms\Domain\Entities\Enrollment;
use Modules\Lms\Domain\Entities\LessonProgress;

/**
 * @api 1.0
 */
interface EnrollmentRepositoryInterface
{
    public function save(Enrollment $enrollment): void;

    public function findById(string $id): ?Enrollment;

    public function find(int $studentId, string $courseId): ?Enrollment;

    public function findByAuthority(string $authority): ?Enrollment;

    /** ثبت‌نام‌های دانشجو (active/completed/pending). @return list<Enrollment> */
    public function forStudent(int $studentId, ?string $status = null): array;

    /** شناسهٔ دوره‌های تکمیل‌شدهٔ دانشجو (بررسی پیش‌نیاز). @return list<string> */
    public function completedCourseIdsOf(int $studentId): array;

    /**
     * ثبت‌نام‌های یک دوره (پنل/گزارش).
     *
     * @return array{rows: list<Enrollment>, total: int}
     */
    public function paginateForCourse(string $courseId, int $page = 1, int $perPage = 20, ?string $status = null): array;

    /**
     * ثبت‌نام‌ها با فیلتر (پنل مدیر).
     *
     * @param array{status?: string|null, course_id?: string|null, student_id?: int|null} $filters
     *
     * @return array{rows: list<Enrollment>, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array;

    public function countByCourseAndStatus(string $courseId, ?string $status = null): int;

    // ── پیشرفت درس ────────────────────────────────────────────────────────

    public function saveProgress(LessonProgress $progress): void;

    public function findProgress(string $enrollmentId, string $lessonId): ?LessonProgress;

    /** @return list<LessonProgress> همهٔ ردیف‌های پیشرفت یک ثبت‌نام */
    public function progressOf(string $enrollmentId): array;

    public function completedLessonCount(string $enrollmentId): int;

    /** آیا درس تکمیل شده؟ */
    public function isLessonCompleted(string $enrollmentId, string $lessonId): bool;
}
