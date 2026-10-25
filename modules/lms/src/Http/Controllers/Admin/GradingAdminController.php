<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * GradingAdminController — صف نمره‌دهی دستی (/admin/lms/grading).
 */

namespace Modules\Lms\Http\Controllers\Admin;

use Modules\Lms\Application\UseCases\QuizService;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * DOC-095 §3.3/§4 — نمره‌دهی دستی (descriptive) توسط مدرس/assistant.
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class GradingAdminController
{
    public function __construct(
        private readonly QuizService $service,
        private readonly QuizRepositoryInterface $quizzes,
        private readonly CourseRepositoryInterface $courses,
        private readonly UserReaderInterface $users,
    ) {
    }

    /** صف تلاش‌های در انتظار نمره — /admin/lms/grading */
    public function index(RequestInterface $request): mixed
    {
        $this->authorizeGrading();

        $page = \max(1, (int) $request->input('page', 1));
        $result = $this->quizzes->paginateAwaitingGrading($page, 20);

        $me = (int) Auth::id();
        $global = Gate::allows('lms.attempt.grade-any');

        $rows = [];
        foreach ($result['rows'] as $attempt) {
            $quiz = $this->quizzes->findQuizById($attempt->quizId());
            $course = $quiz !== null ? $this->courses->findById($quiz->courseId()) : null;

            // 🔒 مدرس فقط صف دوره‌های خودش را می‌بیند (grade-any = سراسری)
            if (!$global && ($course === null || $course->instructorId() !== $me)) {
                continue;
            }

            $rows[] = [
                'attempt' => $attempt,
                'quiz' => $quiz,
                'course' => $course,
                'student' => $this->users->displayNameOf($attempt->studentId(), 'دانشجو'),
            ];
        }

        return View::render('lms::admin.grading-index', [
            'rows' => $rows,
            'total' => $global ? $result['total'] : \count($rows),
            'page' => $page,
            'perPage' => 20,
        ]);
    }

    /** فرم نمره‌دهی — /admin/lms/grading/{attemptId} */
    public function show(string $attemptId): mixed
    {
        [$attempt, $quiz, $course] = $this->attemptContext($attemptId);

        return View::render('lms::admin.grading-form', [
            'attempt' => $attempt,
            'quiz' => $quiz,
            'course' => $course,
            'student' => $this->users->displayNameOf($attempt->studentId(), 'دانشجو'),
            'breakdown' => $this->service->attemptBreakdown($attempt, $quiz),
        ]);
    }

    /** ثبت نمرات دستی — POST /admin/lms/grading/{attemptId} */
    public function grade(RequestInterface $request, string $attemptId): mixed
    {
        [$attempt, $quiz, $course] = $this->attemptContext($attemptId);

        /** @var array<string, mixed> $raw */
        $raw = (array) $request->input('grades', []);
        $grades = [];
        foreach ($raw as $qid => $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $grades[(string) $qid] = [
                'score' => (int) ($entry['score'] ?? 0),
                'note' => (string) ($entry['note'] ?? ''),
            ];
        }

        try {
            $this->service->gradeAttempt($attempt, $quiz, $grades, (int) Auth::id());
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'attempt_graded');

        return redirect('/admin/lms/grading');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @return array{0: \Modules\Lms\Domain\Entities\QuizAttempt, 1: \Modules\Lms\Domain\Entities\Quiz, 2: ?\Modules\Lms\Domain\Entities\Course} */
    private function attemptContext(string $attemptId): array
    {
        $attempt = $this->quizzes->findAttemptById($attemptId);
        if ($attempt === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.attempt_not_found', context: ['id' => $attemptId]);
        }

        $quiz = $this->quizzes->findQuizById($attempt->quizId());
        if ($quiz === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.quiz_not_found');
        }

        $course = $this->courses->findById($quiz->courseId());
        if ($course === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.course_not_found');
        }

        // 🔒 مجوز + مالکیت: مدرسِ دوره یا دارندگان grade-any/admin
        if (!Gate::allows('grade', $course)) {
            throw new ForbiddenException(messageId: 'lms::messages.forbidden', context: ['ability' => 'grade']);
        }

        return [$attempt, $quiz, $course];
    }

    private function authorizeGrading(): void
    {
        if (!Gate::allows('lms.attempt.grade')) {
            throw new ForbiddenException(messageId: 'lms::messages.forbidden', context: ['permission' => 'lms.attempt.grade']);
        }
    }
}
