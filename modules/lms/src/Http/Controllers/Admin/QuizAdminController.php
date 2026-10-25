<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * QuizAdminController — مدیریت آزمون/سؤال (/admin/lms/quizzes).
 */

namespace Modules\Lms\Http\Controllers\Admin;

use Modules\Lms\Application\UseCases\QuizAdminService;
use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/lms/README.md
 */
final class QuizAdminController
{
    public function __construct(
        private readonly QuizAdminService $service,
        private readonly QuizRepositoryInterface $quizzes,
        private readonly CourseRepositoryInterface $courses,
    ) {
    }

    /** آزمون‌های یک دوره — /admin/lms/courses/{id}/quizzes */
    public function index(string $courseId): mixed
    {
        $course = $this->findCourseOr404($courseId);
        $this->authorizeCourse('manageQuiz', $course);

        return View::render('lms::admin.quiz-index', [
            'course' => $course,
            'quizzes' => $this->quizzes->quizzesOfCourse($courseId),
            'lessons' => $this->courses->lessonsOf($courseId),
        ]);
    }

    public function store(RequestInterface $request, string $courseId): mixed
    {
        $course = $this->findCourseOr404($courseId);
        $this->authorizeCourse('manageQuiz', $course);

        $lessonId = (string) $request->input('lesson_id', '');

        try {
            $quiz = $this->service->createQuiz($courseId, [
                'title' => (string) $request->input('title', ''),
                'time_limit' => $request->input('time_limit') === null || $request->input('time_limit') === '' ? null : (int) $request->input('time_limit'),
                'pass_score' => $request->input('pass_score') === null || $request->input('pass_score') === '' ? null : (int) $request->input('pass_score'),
                'max_attempts' => $request->input('max_attempts') === null || $request->input('max_attempts') === '' ? null : (int) $request->input('max_attempts'),
            ], $lessonId !== '' ? $lessonId : null);
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'quiz_created');

        return redirect('/admin/lms/quizzes/' . $quiz->id() . '/edit');
    }

    public function edit(string $quizId): mixed
    {
        $quiz = $this->findQuizOr404($quizId);
        $this->authorizeCourse('manageQuiz', $this->findCourseOr404($quiz->courseId()));

        return View::render('lms::admin.quiz-form', [
            'quiz' => $quiz,
            'course' => $this->courses->findById($quiz->courseId()),
            'questions' => $this->quizzes->questionsOf($quiz->id()),
            'lessons' => $this->courses->lessonsOf($quiz->courseId()),
        ]);
    }

    public function update(RequestInterface $request, string $quizId): mixed
    {
        $quiz = $this->findQuizOr404($quizId);
        $this->authorizeCourse('manageQuiz', $this->findCourseOr404($quiz->courseId()));

        try {
            $this->service->updateQuiz($quiz, [
                'title' => (string) $request->input('title', ''),
                'time_limit' => $request->input('time_limit', ''),
                'pass_score' => $request->input('pass_score') === null || $request->input('pass_score') === '' ? null : (int) $request->input('pass_score'),
                'max_attempts' => $request->input('max_attempts') === null || $request->input('max_attempts') === '' ? null : (int) $request->input('max_attempts'),
            ]);
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'quiz_updated');

        return back();
    }

    public function destroy(string $quizId): mixed
    {
        $quiz = $this->findQuizOr404($quizId);
        $courseId = $quiz->courseId();
        $this->authorizeCourse('manageQuiz', $this->findCourseOr404($courseId));

        $this->service->deleteQuiz($quiz);
        Session::flash('status', 'quiz_deleted');

        return redirect('/admin/lms/courses/' . $courseId . '/quizzes');
    }

    // ─── سؤال‌ها ──────────────────────────────────────────────────────────

    public function addQuestion(RequestInterface $request, string $quizId): mixed
    {
        $quiz = $this->findQuizOr404($quizId);
        $this->authorizeCourse('manageQuiz', $this->findCourseOr404($quiz->courseId()));

        try {
            $this->service->addQuestion($quiz, $this->questionInput($request));
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'question_added');

        return back();
    }

    public function updateQuestion(RequestInterface $request, string $quizId, string $questionId): mixed
    {
        $quiz = $this->findQuizOr404($quizId);
        $this->authorizeCourse('manageQuiz', $this->findCourseOr404($quiz->courseId()));

        $question = $this->quizzes->findQuestionById($questionId);
        if ($question === null || $question->quizId() !== $quiz->id()) {
            throw new RouteNotFoundException(messageId: 'lms::messages.question_not_found');
        }

        try {
            $this->service->updateQuestion($question, $this->questionInput($request));
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'question_updated');

        return back();
    }

    public function deleteQuestion(string $quizId, string $questionId): mixed
    {
        $quiz = $this->findQuizOr404($quizId);
        $this->authorizeCourse('manageQuiz', $this->findCourseOr404($quiz->courseId()));

        $question = $this->quizzes->findQuestionById($questionId);
        if ($question === null || $question->quizId() !== $quiz->id()) {
            throw new RouteNotFoundException(messageId: 'lms::messages.question_not_found');
        }

        $this->service->deleteQuestion($question);
        Session::flash('status', 'question_deleted');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function questionInput(RequestInterface $request): array
    {
        $options = $request->input('options');

        return [
            'type' => (string) $request->input('type', 'multiple_choice'),
            'body' => (string) $request->input('body', ''),
            'options' => \is_array($options) ? $options : (string) ($options ?? ''),
            'correct_answer' => (string) $request->input('correct_answer', ''),
            'score' => (int) $request->input('score', 1),
            'position' => $request->input('position') === null || $request->input('position') === '' ? null : (int) $request->input('position'),
        ];
    }

    private function findCourseOr404(string $id): Course
    {
        $course = $this->courses->findById($id);
        if ($course === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.course_not_found', context: ['id' => $id]);
        }

        return $course;
    }

    private function findQuizOr404(string $id): \Modules\Lms\Domain\Entities\Quiz
    {
        $quiz = $this->quizzes->findQuizById($id);
        if ($quiz === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.quiz_not_found', context: ['id' => $id]);
        }

        return $quiz;
    }

    private function authorizeCourse(string $ability, Course $course): void
    {
        if (!Gate::allows($ability, $course)) {
            throw new ForbiddenException(messageId: 'lms::messages.forbidden', context: ['ability' => $ability]);
        }
    }
}
