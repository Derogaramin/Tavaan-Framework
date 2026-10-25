<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CourseRepositoryInterface — پورت مخزن دوره (+ section/lesson/category/prereq).
 */

namespace Modules\Lms\Domain\Repositories;

use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Entities\Lesson;
use Modules\Lms\Domain\Entities\LmsCategory;
use Modules\Lms\Domain\Entities\Section;

/**
 * @api 1.0
 */
interface CourseRepositoryInterface
{
    public function save(Course $course): void;

    public function findById(string $id): ?Course;

    public function findBySlug(string $slug): ?Course;

    public function slugExists(string $slug): bool;

    public function delete(string $id): void;

    /**
     * فهرست صفحه‌بندی‌شده.
     *
     * @param array{status?: string|null, category_id?: int|null, instructor_id?: int|null, search?: string, level?: string, sort?: string} $filters
     *
     * @return array{rows: list<Course>, total: int, page: int, perPage: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 12): array;

    /**
     * فهرست صفحه‌بندی‌شده (جست‌وجوی LIKE با واریانت رقم — fallback موتور search).
     *
     * @return array{rows: list<Course>, total: int}
     */
    public function searchPublished(string $query, int $page = 1, int $perPage = 12): array;

    /** ساختار کامل دوره: sections (مرتب) با lessons هر بخش.
     *
     * @return list<array{section: Section, lessons: list<Lesson>}>
     */
    public function structureOf(string $courseId): array;

    /** همهٔ درس‌های دوره (مرتب بر اساس section.position, lesson.position).
     *
     * @return list<Lesson>
     */
    public function lessonsOf(string $courseId): array;

    public function findLesson(string $lessonId): ?Lesson;

    public function saveSection(Section $section): void;

    public function deleteSection(string $sectionId): void;

    public function saveLesson(Lesson $lesson): void;

    public function deleteLesson(string $lessonId): void;

    /** @return list<string> */
    public function prerequisiteIdsOf(string $courseId): array;

    /** @param list<string> $prerequisiteIds */
    public function replacePrerequisites(string $courseId, array $prerequisiteIds): void;

    // ── دسته‌ها ───────────────────────────────────────────────────────────

    /** @return list<LmsCategory> */
    public function allCategories(): array;

    public function findCategory(int $id): ?LmsCategory;

    public function findCategoryBySlug(string $slug): ?LmsCategory;

    public function saveCategory(LmsCategory $category): int;

    public function deleteCategory(int $id): void;

    // ── شمارنده‌ها ────────────────────────────────────────────────────────

    public function incrementStudentCount(string $courseId, int $delta = 1): void;

    public function refreshDuration(string $courseId): int;

    /** دوره‌های منتشرشدهٔ هم‌دسته (پیشنهاد در صفحهٔ دوره). @return list<Course> */
    public function related(Course $course, int $limit = 4): array;
}
