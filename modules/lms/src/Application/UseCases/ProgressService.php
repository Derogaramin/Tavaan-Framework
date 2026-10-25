<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * ProgressService — پیشرفت درس/دوره (DOC-095 §3.2).
 */

namespace Modules\Lms\Application\UseCases;

use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Entities\Enrollment;
use Modules\Lms\Domain\Entities\Lesson;
use Modules\Lms\Domain\Entities\LessonProgress;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Modules\Lms\Domain\Services\ProgressCalculator;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;

/**
 * ProgressService — جریان §3.2:
 *
 * ```
 * complete lesson → progress row → recalc percent
 *   → همهٔ درس‌ها تمام؟ → enrollment completed → event
 *   → certificate check (همهٔ آزمون‌ها pass)
 * ```
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class ProgressService
{
    /**
     * @param array<string, mixed> $config کلید config('lms')
     */
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly CourseRepositoryInterface $courses,
        private readonly QuizRepositoryInterface $quizzes,
        private readonly ProgressCalculator $calculator,
        private readonly CertificateService $certificates,
        private readonly array $config = [],
    ) {
    }

    /** بازکردن درس — in_progress (بدون بازگشت از completed). */
    public function markLessonStarted(Enrollment $enrollment, Lesson $lesson): void
    {
        $this->assertAccess($enrollment, $lesson);

        $progress = $this->enrollments->findProgress($enrollment->id(), $lesson->id())
            ?? LessonProgress::start($enrollment->id(), $lesson->id());

        $progress->markInProgress();
        $this->enrollments->saveProgress($progress);
    }

    /**
     * تکمیل درس (DOC-095 §3.2) — idempotent.
     *
     * @return array{enrollment: Enrollment, progressPercent: float, courseCompleted: bool}
     */
    public function markLessonComplete(Enrollment $enrollment, Lesson $lesson): array
    {
        $this->assertAccess($enrollment, $lesson);

        $progress = $this->enrollments->findProgress($enrollment->id(), $lesson->id())
            ?? LessonProgress::start($enrollment->id(), $lesson->id());

        $alreadyCompleted = $progress->isCompleted();
        $progress->markCompleted();
        $this->enrollments->saveProgress($progress);

        return $this->recalculate($enrollment, $lesson, $alreadyCompleted ? null : $progress);
    }

    /**
     * تکمیل درسِ نوع quiz با قبولی آزمون (config: progress.complete_quiz_lesson_on_pass).
     *
     * @return array{enrollment: Enrollment, progressPercent: float, courseCompleted: bool}|null
     */
    public function completeQuizLesson(int $studentId, string $courseId, string $quizId): ?array
    {
        if (!(bool) ($this->config['progress']['complete_quiz_lesson_on_pass'] ?? true)) {
            return null;
        }

        $enrollment = $this->enrollments->find($studentId, $courseId);
        if ($enrollment === null || !$enrollment->isActive()) {
            return null;
        }

        foreach ($this->courses->lessonsOf($courseId) as $lesson) {
            if ($lesson->quizId() === $quizId) {
                $result = $this->markLessonComplete($enrollment, $lesson);

                return $result;
            }
        }

        // آزمون بدون درسِ متناظر — فقط بررسی صدور گواهی
        return $this->maybeIssueCertificate($enrollment) ? ['enrollment' => $enrollment, 'progressPercent' => $enrollment->progressPercent(), 'courseCompleted' => $enrollment->isCompleted()] : null;
    }

    /**
     * بازشماری پیشرفت ثبت‌نام (maintenance — `lms:recompute-progress`).
     */
    public function recompute(Enrollment $enrollment): Enrollment
    {
        $course = $this->courses->findById($enrollment->courseId());
        if ($course === null) {
            return $enrollment;
        }

        $totalLessons = \count($this->courses->lessonsOf($course->id()));
        $completed = $this->enrollments->completedLessonCount($enrollment->id());
        $percent = $this->calculator->percent($completed, $totalLessons);

        $enrollment->updateProgress($percent);

        if ($this->calculator->isCourseComplete($completed, $totalLessons) && $enrollment->status()->grantsAccess() && !$enrollment->isCompleted()) {
            $enrollment->complete($course->title());
            $this->flushEvents($enrollment);
        }

        $this->enrollments->save($enrollment);

        return $enrollment;
    }

    /**
     * بررسی صدور گواهی (DOC-095 §3.4):
     * enrollment completed + همهٔ آزمون‌ها pass → issue.
     */
    public function maybeIssueCertificate(Enrollment $enrollment): bool
    {
        if (!(bool) ($this->config['certificates']['auto_issue'] ?? true)) {
            return false;
        }
        if (!$enrollment->isCompleted()) {
            return false;
        }

        $pass = $this->quizzes->passStatusForStudentInCourse($enrollment->studentId(), $enrollment->courseId());
        if ($pass['total'] > 0 && $pass['passed'] < $pass['total']) {
            return false;   // آزمونِ باقی‌مانده → هنوز گواهی نه
        }

        return $this->certificates->issueForEnrollment($enrollment) !== null;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @return array{enrollment: Enrollment, progressPercent: float, courseCompleted: bool}
     */
    private function recalculate(Enrollment $enrollment, Lesson $lesson, ?LessonProgress $freshProgress): array
    {
        $course = $this->courses->findById($enrollment->courseId());
        if ($course === null) {
            throw new ValidationException(
                errors: ['course' => ['دوره یافت نشد.']],
                messageId: 'lms::messages.course_not_found',
            );
        }

        $totalLessons = \count($this->courses->lessonsOf($course->id()));
        $completed = $this->enrollments->completedLessonCount($enrollment->id());
        $percent = $this->calculator->percent($completed, $totalLessons);

        $enrollment->updateProgress($percent);

        if ($freshProgress !== null) {
            $event = new \Modules\Lms\Domain\Events\LessonWasCompleted(
                $enrollment->id(),
                $enrollment->studentId(),
                $course->id(),
                $lesson->id(),
                $lesson->title(),
                $percent,
            );
            Event::dispatch($event->eventName(), $event->toPayload());
        }

        $courseCompleted = false;
        if ($this->calculator->isCourseComplete($completed, $totalLessons) && !$enrollment->isCompleted()) {
            $enrollment->complete($course->title());
            $this->flushEvents($enrollment);   // → lms.course.completed (welcome/certificate listener)
            $courseCompleted = true;
        }

        $this->enrollments->save($enrollment);

        if ($courseCompleted) {
            $this->maybeIssueCertificate($enrollment);
        }

        return [
            'enrollment' => $enrollment,
            'progressPercent' => $percent,
            'courseCompleted' => $courseCompleted,
        ];
    }

    private function assertAccess(Enrollment $enrollment, Lesson $lesson): void
    {
        if ($lesson->courseId() !== $enrollment->courseId()) {
            throw new ValidationException(
                errors: ['lesson' => ['درس به این دوره تعلق ندارد.']],
                messageId: 'lms::messages.lesson_course_mismatch',
            );
        }
        if (!$enrollment->status()->grantsAccess()) {
            throw new ValidationException(
                errors: ['enrollment' => ['ثبت‌نام فعالی برای این دوره ندارید.']],
                messageId: 'lms::messages.not_enrolled',
            );
        }
    }

    private function flushEvents(Enrollment $enrollment): void
    {
        foreach ($enrollment->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
