<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * QuizController — آزمون دانشجو (DOC-095 §3.3).
 */

namespace Modules\Lms\Http\Controllers;

use Modules\Lms\Application\UseCases\QuizService;
use Modules\Lms\Domain\Enums\AttemptStatus;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * مسیرها (DOC-095 §3.3):
 * - GET  /lms/quizzes/{id}            → صفحهٔ آزمون (وضعیت تلاش‌ها)
 * - POST /lms/quizzes/{id}/attempt    → شروع/ادامهٔ تلاش
 * - POST /lms/attempts/{id}/submit    → ثبت پاسخ‌ها + نمره‌دهی خودکار
 * - GET  /lms/attempts/{id}/result    → کارنامه
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class QuizController
{
    public function __construct(
        private readonly QuizService $service,
        private readonly QuizRepositoryInterface $quizzes,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly CourseRepositoryInterface $courses,
    ) {
    }

    /** صفحهٔ آزمون — وضعیت + شروع. */
    public function show(RequestInterface $request, string $id): mixed
    {
        $studentId = $this->studentIdOrFail();

        $quiz = $this->quizzes->findQuizById($id);
        if ($quiz === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.quiz_not_found', context: ['id' => $id]);
        }

        $enrollment = $this->enrollments->find($studentId, $quiz->courseId());
        if ($enrollment === null || !$enrollment->status()->grantsAccess()) {
            throw new ForbiddenException(messageId: 'lms::messages.not_enrolled');
        }

        $course = $this->courses->findById($quiz->courseId());
        $attempts = $this->quizzes->attemptsOf($studentId, $quiz->id());
        $active = null;
        foreach ($attempts as $attempt) {
            if ($attempt->status() === AttemptStatus::InProgress && $quiz->isWithinTimeLimit($attempt->startedAt())) {
                $active = $attempt;
            }
        }

        return View::render('lms::front.quiz', [
            'quiz' => $quiz,
            'course' => $course,
            'enrollment' => $enrollment,
            'attempts' => $attempts,
            'activeAttempt' => $active,
            'questions' => $active !== null ? $this->service->questionsOf($quiz) : [],
            'remainingAttempts' => \max(0, $quiz->maxAttempts() - \count($attempts)),
            'alreadyPassed' => $this->quizzes->hasPassedAttempt($studentId, $quiz->id()),
        ]);
    }

    /** شروع تلاش — POST /lms/quizzes/{id}/attempt (DOC-095 §3.3). */
    public function startAttempt(RequestInterface $request, string $id): mixed
    {
        $studentId = $this->studentIdOrFail();

        $quiz = $this->quizzes->findQuizById($id);
        if ($quiz === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.quiz_not_found');
        }

        $enrollment = $this->enrollments->find($studentId, $quiz->courseId());
        if ($enrollment === null) {
            throw new ForbiddenException(messageId: 'lms::messages.not_enrolled');
        }

        try {
            $attempt = $this->service->startAttempt($enrollment, $quiz);
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        return redirect('/lms/quizzes/' . $quiz->id() . '?attempt=' . $attempt->id());
    }

    /** فرم پاسخ‌ها (تلاش در جریان). */
    public function attemptForm(RequestInterface $request, string $id): mixed
    {
        [$attempt, $quiz] = $this->attemptContext($id);

        if ($attempt->status() !== AttemptStatus::InProgress) {
            return redirect('/lms/attempts/' . $attempt->id() . '/result');
        }

        $course = $this->courses->findById($quiz->courseId());

        return View::render('lms::front.quiz-attempt', [
            'attempt' => $attempt,
            'quiz' => $quiz,
            'course' => $course,
            'questions' => $this->service->questionsOf($quiz),
            'deadline' => $quiz->deadlineOf($attempt->startedAt()),
        ]);
    }

    /** ثبت پاسخ‌ها — POST /lms/attempts/{id}/submit. */
    public function submit(RequestInterface $request, string $id): mixed
    {
        [$attempt, $quiz] = $this->attemptContext($id);

        /** @var array<string, mixed> $raw */
        $raw = (array) $request->input('answers', []);
        $answers = [];
        foreach ($raw as $qid => $value) {
            $answers[(string) $qid] = \is_scalar($value) ? (string) $value : '';
        }

        try {
            $result = $this->service->submitAttempt($attempt, $quiz, $answers);
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', $result['awaitingManual'] ? 'awaiting_grading' : ($result['passed'] ? 'quiz_passed' : 'quiz_failed'));

        return redirect('/lms/attempts/' . $attempt->id() . '/result');
    }

    /** کارنامهٔ تلاش. */
    public function result(RequestInterface $request, string $id): mixed
    {
        [$attempt, $quiz] = $this->attemptContext($id);

        $course = $this->courses->findById($quiz->courseId());

        return View::render('lms::front.attempt-result', [
            'attempt' => $attempt,
            'quiz' => $quiz,
            'course' => $course,
            'breakdown' => $attempt->isGraded() || $attempt->isAwaitingManualGrading()
                ? $this->service->attemptBreakdown($attempt, $quiz)
                : [],
        ]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @return array{0: \Modules\Lms\Domain\Entities\QuizAttempt, 1: \Modules\Lms\Domain\Entities\Quiz} */
    private function attemptContext(string $attemptId): array
    {
        $studentId = $this->studentIdOrFail();

        $attempt = $this->quizzes->findAttemptById($attemptId);
        if ($attempt === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.attempt_not_found', context: ['id' => $attemptId]);
        }
        if ($attempt->studentId() !== $studentId) {
            throw new ForbiddenException(messageId: 'lms::messages.attempt_forbidden');
        }

        $quiz = $this->quizzes->findQuizById($attempt->quizId());
        if ($quiz === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.quiz_not_found');
        }

        return [$attempt, $quiz];
    }

    private function studentIdOrFail(): int
    {
        $user = Auth::user();
        if ($user === null) {
            throw new ForbiddenException(messageId: 'lms::messages.login_required');
        }

        return (int) $user->getAuthIdentifier();
    }
}
