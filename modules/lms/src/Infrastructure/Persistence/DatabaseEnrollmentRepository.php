<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * DatabaseEnrollmentRepository — آداپتر ثبت‌نام + پیشرفت درس.
 */

namespace Modules\Lms\Infrastructure\Persistence;

use Modules\Lms\Domain\Entities\Enrollment;
use Modules\Lms\Domain\Entities\LessonProgress;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/lms/README.md
 */
final class DatabaseEnrollmentRepository implements EnrollmentRepositoryInterface
{
    public function save(Enrollment $enrollment): void
    {
        $row = $enrollment->toRow();
        $exists = DB::table('lms_enrollments')->where('id', $enrollment->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('lms_enrollments')->where('id', $enrollment->id())->update($row);
        } else {
            DB::table('lms_enrollments')->insert($row);
        }
    }

    public function findById(string $id): ?Enrollment
    {
        $row = DB::table('lms_enrollments')->where('id', $id)->first();

        return $row === null ? null : Enrollment::fromRow($row);
    }

    public function find(int $studentId, string $courseId): ?Enrollment
    {
        $row = DB::table('lms_enrollments')
            ->where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->first();

        return $row === null ? null : Enrollment::fromRow($row);
    }

    public function findByAuthority(string $authority): ?Enrollment
    {
        if ($authority === '') {
            return null;
        }

        $row = DB::table('lms_enrollments')->where('payment_authority', $authority)->first();

        return $row === null ? null : Enrollment::fromRow($row);
    }

    public function forStudent(int $studentId, ?string $status = null): array
    {
        $query = DB::table('lms_enrollments')->where('student_id', $studentId);
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return \array_map(static fn (array $row): Enrollment => Enrollment::fromRow($row), $rows);
    }

    public function completedCourseIdsOf(int $studentId): array
    {
        $rows = DB::table('lms_enrollments')
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->get();

        return \array_values(\array_unique(\array_map(
            static fn (array $row): string => (string) $row['course_id'],
            $rows,
        )));
    }

    public function paginateForCourse(string $courseId, int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        return $this->paginate(['course_id' => $courseId, 'status' => $status], $page, $perPage);
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('lms_enrollments');
        $rows = DB::table('lms_enrollments');

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['status']) && (string) $filters['status'] !== '') {
                $q->where('status', (string) $filters['status']);
            }
            if (isset($filters['course_id']) && (string) $filters['course_id'] !== '') {
                $q->where('course_id', (string) $filters['course_id']);
            }
            if (isset($filters['student_id']) && $filters['student_id'] !== null) {
                $q->where('student_id', (int) $filters['student_id']);
            }
        };
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): Enrollment => Enrollment::fromRow($row), $data),
            'total' => $total,
        ];
    }

    public function countByCourseAndStatus(string $courseId, ?string $status = null): int
    {
        $query = DB::table('lms_enrollments')->where('course_id', $courseId);
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query->count();
    }

    // ── پیشرفت درس ────────────────────────────────────────────────────────

    public function saveProgress(LessonProgress $progress): void
    {
        $row = $progress->toRow();
        $exists = DB::table('lms_lesson_progress')
            ->where('enrollment_id', $progress->enrollmentId())
            ->where('lesson_id', $progress->lessonId())
            ->first();

        if ($exists !== null) {
            DB::table('lms_lesson_progress')
                ->where('enrollment_id', $progress->enrollmentId())
                ->where('lesson_id', $progress->lessonId())
                ->update(['status' => $row['status'], 'completed_at' => $row['completed_at']]);
        } else {
            DB::table('lms_lesson_progress')->insert($row);
        }
    }

    public function findProgress(string $enrollmentId, string $lessonId): ?LessonProgress
    {
        $row = DB::table('lms_lesson_progress')
            ->where('enrollment_id', $enrollmentId)
            ->where('lesson_id', $lessonId)
            ->first();

        return $row === null ? null : LessonProgress::fromRow($row);
    }

    public function progressOf(string $enrollmentId): array
    {
        $rows = DB::table('lms_lesson_progress')->where('enrollment_id', $enrollmentId)->get();

        return \array_map(static fn (array $row): LessonProgress => LessonProgress::fromRow($row), $rows);
    }

    public function completedLessonCount(string $enrollmentId): int
    {
        return DB::table('lms_lesson_progress')
            ->where('enrollment_id', $enrollmentId)
            ->where('status', 'completed')
            ->count();
    }

    public function isLessonCompleted(string $enrollmentId, string $lessonId): bool
    {
        return DB::table('lms_lesson_progress')
            ->where('enrollment_id', $enrollmentId)
            ->where('lesson_id', $lessonId)
            ->where('status', 'completed')
            ->exists();
    }
}
