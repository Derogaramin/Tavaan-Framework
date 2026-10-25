<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * ReportService — گزارش پیشرفت (DOC-095 §6: report engine).
 */

namespace Modules\Lms\Application\UseCases;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * ReportService — تجمع پیشرفت:
 *
 * - به‌ازای هر دوره: ثبت‌نام‌ها (تفکیک وضعیت)، میانگین پیشرفت، تکمیل‌شده‌ها،
 *   نرخ تکمیل و وضعیت آزمون‌ها.
 * - خروجی HTML (پنل) و CSV (دانلود — BOM + ضد CSV Injection، الگوی report).
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class ReportService
{
    public function __construct(
        private readonly CourseRepositoryInterface $courses,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly QuizRepositoryInterface $quizzes,
        private readonly UserReaderInterface $users,
    ) {
    }

    /**
     * گزارش یک دوره.
     *
     * @return array{course: Course, enrollments: int, active: int, completed: int, pending: int, dropped: int, avg_progress: float, completion_rate: float, students: list<array<string, mixed>>}
     */
    public function courseProgress(Course $course): array
    {
        $result = $this->enrollments->paginate(['course_id' => $course->id()], 1, 100);
        $rows = $result['rows'];

        $active = $completed = $pending = $dropped = 0;
        $progressSum = 0.0;
        $progressCount = 0;
        $students = [];

        foreach ($rows as $enrollment) {
            match ($enrollment->status()->value) {
                'active' => $active++,
                'completed' => $completed++,
                'pending_payment' => $pending++,
                default => $dropped++,
            };

            if ($enrollment->status()->grantsAccess()) {
                $progressSum += $enrollment->progressPercent();
                $progressCount++;
            }

            $students[] = [
                'student_id' => $enrollment->studentId(),
                'student' => $this->users->displayNameOf($enrollment->studentId(), 'دانشجو'),
                'status' => $enrollment->status()->labelFa(),
                'progress' => $enrollment->progressPercent(),
                'enrolled_at' => $enrollment->enrolledAt(),
                'completed_at' => $enrollment->completedAt(),
                'quizzes' => $this->quizzes->passStatusForStudentInCourse($enrollment->studentId(), $course->id()),
            ];
        }

        $granted = $active + $completed;

        return [
            'course' => $course,
            'enrollments' => $result['total'],
            'active' => $active,
            'completed' => $completed,
            'pending' => $pending,
            'dropped' => $dropped,
            'avg_progress' => $progressCount > 0 ? \round($progressSum / $progressCount, 2) : 0.0,
            'completion_rate' => $granted > 0 ? \round($completed * 100 / $granted, 1) : 0.0,
            'students' => $students,
        ];
    }

    /**
     * خلاصهٔ همهٔ دوره‌ها (پنل مدیر) یا دوره‌های یک مدرس.
     *
     * @return list<array<string, mixed>>
     */
    public function overview(?int $instructorId = null): array
    {
        $filters = ['status' => null];
        if ($instructorId !== null) {
            $filters['instructor_id'] = $instructorId;
        }

        $result = $this->courses->paginate($filters, 1, 100);

        $rows = [];
        foreach ($result['rows'] as $course) {
            $summary = $this->courseProgress($course);
            $rows[] = [
                'course' => $course,
                'instructor' => $this->users->displayNameOf($course->instructorId(), 'مدرس'),
                'enrollments' => $summary['enrollments'],
                'completed' => $summary['completed'],
                'avg_progress' => $summary['avg_progress'],
                'completion_rate' => $summary['completion_rate'],
                'revenue' => $this->revenueOf($course),
            ];
        }

        return $rows;
    }

    /** درآمد تقریبی دوره (ثبت‌نام‌های پرداخت‌شده × قیمت) — minor-unit. */
    public function revenueOf(Course $course): int
    {
        if ($course->isFree()) {
            return 0;
        }

        $paid = DB::table('lms_enrollments')
            ->where('course_id', $course->id())
            ->whereIn('status', ['active', 'completed'])
            ->whereNotNull('payment_authority')
            ->count();

        return $paid * $course->price()->minor;
    }

    /**
     * CSV گزارش (BOM + ضد CSV Injection — الگوی موتور report).
     *
     * @param list<array<string, mixed>> $rows خروجی overview()
     */
    public function toCsv(array $rows): string
    {
        $lines = ['course,slug,instructor,status,enrollments,completed,avg_progress,completion_rate,revenue_minor,currency'];

        foreach ($rows as $row) {
            /** @var Course $course */
            $course = $row['course'];
            $lines[] = \implode(',', [
                self::csvSafe($course->title()),
                self::csvSafe($course->slug()),
                self::csvSafe((string) $row['instructor']),
                $course->status()->value,
                (string) (int) $row['enrollments'],
                (string) (int) $row['completed'],
                \number_format((float) $row['avg_progress'], 2, '.', ''),
                \number_format((float) $row['completion_rate'], 1, '.', ''),
                (string) (int) $row['revenue'],
                $course->price()->currency,
            ]);
        }

        return "\xEF\xBB\xBF" . \implode("\n", $lines);
    }

    /** 🔒 ضد CSV Injection — سلول‌های خطرناک با ' prefix. */
    private static function csvSafe(string $value): string
    {
        if ($value !== '' && \str_contains("=+-@\t\r", $value[0])) {
            $value = "'" . $value;
        }

        return '"' . \str_replace('"', '""', $value) . '"';
    }
}
