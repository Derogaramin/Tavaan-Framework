<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * DatabaseCourseRepository — آداپتر دوره/بخش/درس/دسته/پیش‌نیاز.
 */

namespace Modules\Lms\Infrastructure\Persistence;

use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Entities\Lesson;
use Modules\Lms\Domain\Entities\LmsCategory;
use Modules\Lms\Domain\Entities\Section;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * 🔒 جست‌وجوی fallback با واریانت رقم فارسی/لاتین (الگوی انجمن) —
 * ایندکس native موتور search فارسی‌آگاه است ولی LIKE روی DB نه.
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class DatabaseCourseRepository implements CourseRepositoryInterface
{
    public function save(Course $course): void
    {
        $row = $course->toRow();
        $exists = DB::table('lms_courses')->where('id', $course->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('lms_courses')->where('id', $course->id())->update($row);
        } else {
            DB::table('lms_courses')->insert($row);
        }
    }

    public function findById(string $id): ?Course
    {
        $row = DB::table('lms_courses')->where('id', $id)->first();

        return $row === null ? null : Course::fromRow($row, $this->prerequisiteIdsOf($id));
    }

    public function findBySlug(string $slug): ?Course
    {
        $row = DB::table('lms_courses')->where('slug', $slug)->first();

        return $row === null ? null : Course::fromRow($row, $this->prerequisiteIdsOf((string) $row['id']));
    }

    public function slugExists(string $slug): bool
    {
        return DB::table('lms_courses')->where('slug', $slug)->exists();
    }

    public function delete(string $id): void
    {
        DB::table('lms_course_prerequisites')->where('course_id', $id)->orWhere('prerequisite_id', $id)->delete();
        DB::table('lms_lessons')->where('course_id', $id)->delete();
        DB::table('lms_sections')->where('course_id', $id)->delete();
        DB::table('lms_courses')->where('id', $id)->delete();
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('lms_courses');
        $rows = DB::table('lms_courses');

        foreach ($this->applyFilters($count, $filters) as $key => $value) {
            $rows->where($key, $value);
        }

        if (($search = (string) ($filters['search'] ?? '')) !== '') {
            $like = '%' . $search . '%';
            $rows->where(static function (mixed $q) use ($like): void {
                $q->where('title', 'LIKE', $like)->orWhere('summary', 'LIKE', $like);
            });
        }

        $sort = (string) ($filters['sort'] ?? 'newest');
        match ($sort) {
            'popular' => [$rows->orderByDesc('student_count'), $rows->orderByDesc('published_at')],
            'rating' => [$rows->orderByDesc('rating_avg'), $rows->orderByDesc('published_at')],
            'title' => $rows->orderBy('title'),
            default => $rows->orderByDesc('published_at')->orderByDesc('created_at'),
        };

        $total = $count->count();
        $data = $rows->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(fn (array $row): Course => Course::fromRow($row, $this->prerequisiteIdsOf((string) $row['id'])), $data),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function searchPublished(string $query, int $page = 1, int $perPage = 12): array
    {
        // هر واریانت رقم جدا جست‌وجو و با id یکتا می‌شود (الگوی انجمن — بدون OR تودرتو)
        $merged = [];
        $total = 0;
        foreach ($this->digitVariants($query) as $variant) {
            $result = $this->paginate(['status' => 'published', 'search' => $variant], $page, $perPage);
            $total = \max($total, $result['total']);
            foreach ($result['rows'] as $course) {
                $merged[$course->id()] = $course;
            }
        }

        return [
            'rows' => \array_values($merged),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function structureOf(string $courseId): array
    {
        $sections = DB::table('lms_sections')->where('course_id', $courseId)->orderBy('position')->orderBy('id')->get();
        $lessons = DB::table('lms_lessons')->where('course_id', $courseId)->orderBy('position')->orderBy('id')->get();

        $bySection = [];
        foreach ($lessons as $row) {
            $bySection[(string) $row['section_id']][] = Lesson::fromRow($row);
        }

        $structure = [];
        foreach ($sections as $row) {
            $section = Section::fromRow($row);
            $structure[] = [
                'section' => $section,
                'lessons' => $bySection[$section->id()] ?? [],
            ];
        }

        return $structure;
    }

    public function lessonsOf(string $courseId): array
    {
        $rows = DB::table('lms_lessons')
            ->where('lms_lessons.course_id', $courseId)
            ->join('lms_sections', 'lms_sections.id', '=', 'lms_lessons.section_id')
            ->orderBy('lms_sections.position')
            ->orderBy('lms_sections.id')
            ->orderBy('lms_lessons.position')
            ->orderBy('lms_lessons.id')
            ->select('lms_lessons.*')
            ->get();

        return \array_map(static fn (array $row): Lesson => Lesson::fromRow($row), $rows);
    }

    public function findLesson(string $lessonId): ?Lesson
    {
        $row = DB::table('lms_lessons')->where('id', $lessonId)->first();

        return $row === null ? null : Lesson::fromRow($row);
    }

    public function saveSection(Section $section): void
    {
        $row = $section->toRow();
        $exists = DB::table('lms_sections')->where('id', $section->id())->exists();

        if ($exists) {
            unset($row['id']);
            DB::table('lms_sections')->where('id', $section->id())->update($row);
        } else {
            DB::table('lms_sections')->insert($row);
        }
    }

    public function deleteSection(string $sectionId): void
    {
        DB::table('lms_lessons')->where('section_id', $sectionId)->delete();
        DB::table('lms_sections')->where('id', $sectionId)->delete();
    }

    public function saveLesson(Lesson $lesson): void
    {
        $row = $lesson->toRow();
        $exists = DB::table('lms_lessons')->where('id', $lesson->id())->exists();

        if ($exists) {
            unset($row['id']);
            DB::table('lms_lessons')->where('id', $lesson->id())->update($row);
        } else {
            DB::table('lms_lessons')->insert($row);
        }
    }

    public function deleteLesson(string $lessonId): void
    {
        DB::table('lms_lesson_progress')->where('lesson_id', $lessonId)->delete();
        DB::table('lms_lessons')->where('id', $lessonId)->delete();
    }

    public function prerequisiteIdsOf(string $courseId): array
    {
        $rows = DB::table('lms_course_prerequisites')->where('course_id', $courseId)->get();

        return \array_map(static fn (array $row): string => (string) $row['prerequisite_id'], $rows);
    }

    public function replacePrerequisites(string $courseId, array $prerequisiteIds): void
    {
        DB::table('lms_course_prerequisites')->where('course_id', $courseId)->delete();
        foreach (\array_unique($prerequisiteIds) as $pid) {
            if ($pid === '' || $pid === $courseId) {
                continue;
            }
            DB::table('lms_course_prerequisites')->insert([
                'course_id' => $courseId,
                'prerequisite_id' => $pid,
            ]);
        }
    }

    // ── دسته‌ها ───────────────────────────────────────────────────────────

    public function allCategories(): array
    {
        $rows = DB::table('lms_categories')->orderBy('title')->get();

        return \array_map(static fn (array $row): LmsCategory => LmsCategory::fromRow($row), $rows);
    }

    public function findCategory(int $id): ?LmsCategory
    {
        $row = DB::table('lms_categories')->where('id', $id)->first();

        return $row === null ? null : LmsCategory::fromRow($row);
    }

    public function findCategoryBySlug(string $slug): ?LmsCategory
    {
        $row = DB::table('lms_categories')->where('slug', $slug)->first();

        return $row === null ? null : LmsCategory::fromRow($row);
    }

    public function saveCategory(LmsCategory $category): int
    {
        if ($category->id() > 0) {
            DB::table('lms_categories')->where('id', $category->id())->update($category->toRow());

            return $category->id();
        }

        return (int) DB::table('lms_categories')->insertGetId($category->toRow());
    }

    public function deleteCategory(int $id): void
    {
        DB::table('lms_courses')->where('category_id', $id)->update(['category_id' => null]);
        DB::table('lms_categories')->where('id', $id)->delete();
    }

    // ── شمارنده‌ها ────────────────────────────────────────────────────────

    public function incrementStudentCount(string $courseId, int $delta = 1): void
    {
        DB::table('lms_courses')->where('id', $courseId)->increment('student_count', $delta);
    }

    public function refreshDuration(string $courseId): int
    {
        $rows = DB::table('lms_lessons')->where('course_id', $courseId)->get();
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) ($row['duration'] ?? 0);
        }

        DB::table('lms_courses')->where('id', $courseId)->update(['total_duration' => $total]);

        return $total;
    }

    public function related(Course $course, int $limit = 4): array
    {
        $query = DB::table('lms_courses')
            ->where('status', 'published')
            ->where('id', '!=', $course->id());

        if ($course->categoryId() !== null) {
            $query->where('category_id', $course->categoryId());
        }

        $rows = $query->orderByDesc('student_count')->orderByDesc('published_at')->limit($limit)->get();

        return \array_map(static fn (array $row): Course => Course::fromRow($row), $rows);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * فیلترهای قطعی (status/category/instructor/level) روی هر دو query.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function applyFilters(mixed $count, array $filters): array
    {
        $equal = [];

        if (isset($filters['status']) && (string) $filters['status'] !== '') {
            $equal['status'] = (string) $filters['status'];
        }
        if (isset($filters['category_id']) && $filters['category_id'] !== null && $filters['category_id'] !== '') {
            $equal['category_id'] = (int) $filters['category_id'];
        }
        if (isset($filters['instructor_id']) && $filters['instructor_id'] !== null) {
            $equal['instructor_id'] = (int) $filters['instructor_id'];
        }
        if (isset($filters['level']) && (string) $filters['level'] !== '') {
            $equal['level'] = (string) $filters['level'];
        }

        foreach ($equal as $key => $value) {
            $count->where($key, $value);
        }

        return $equal;
    }

    /** واریانت‌های رقم فارسی/لاتین برای LIKE (الگوی انجمن). @return list<string> */
    private function digitVariants(string $term): array
    {
        $latin = \str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $term,
        );
        $persian = \str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            $latin,
        );

        $variants = [$term, $latin];
        if ($persian !== $latin) {
            $variants[] = $persian;
        }

        return \array_values(\array_unique(\array_filter($variants, static fn (string $v): bool => $v !== '')));
    }
}
