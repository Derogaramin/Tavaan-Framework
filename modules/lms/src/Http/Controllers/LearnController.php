<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * LearnController — فضای یادگیری دانشجو (DOC-095 §3.2).
 */

namespace Modules\Lms\Http\Controllers;

use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Lms\Application\UseCases\ProgressService;
use Modules\Lms\Domain\Repositories\CertificateRepositoryInterface;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Modules\Lms\Infrastructure\Persistence\DatabaseLmsCommentStore;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * LearnController — دوره‌های من، پخش درس، تکمیل درس و پرسش‌وپاسخ.
 *
 * دسترسی: محتوای درس فقط برای ثبت‌نام فعال (یا درس is_free_preview
 * در دورهٔ منتشرشده). گارد احراز هویت روی گروه روت (AuthenticateMiddleware).
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class LearnController
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly CourseRepositoryInterface $courses,
        private readonly QuizRepositoryInterface $quizzes,
        private readonly CertificateRepositoryInterface $certificates,
        private readonly ProgressService $progress,
        private readonly UserReaderInterface $users,
        private readonly DatabaseLmsCommentStore $qaStore,
        private readonly CommentThreadBuilder $threads,
        private readonly CommentModerator $moderator,
    ) {
    }

    /** دوره‌های من — /lms/learn */
    public function myCourses(RequestInterface $request): mixed
    {
        $studentId = $this->studentIdOrFail();

        $items = [];
        foreach ($this->enrollments->forStudent($studentId) as $enrollment) {
            $course = $this->courses->findById($enrollment->courseId());
            if ($course === null) {
                continue;
            }
            $items[] = [
                'enrollment' => $enrollment,
                'course' => $course,
                'instructor' => $this->users->displayNameOf($course->instructorId(), 'مدرس'),
                'certificate' => $enrollment->isCompleted()
                    ? $this->certificates->findForEnrollment($enrollment->id())
                    : null,
            ];
        }

        return View::render('lms::front.learn-index', [
            'items' => $items,
        ]);
    }

    /** صفحهٔ دوره در فضای یادگیری (سرفصل‌ها + پیشرفت) — /lms/learn/{slug} */
    public function coursePlayer(RequestInterface $request, string $slug): mixed
    {
        $course = $this->courses->findBySlug($slug);
        if ($course === null || !$course->isPublished()) {
            throw new RouteNotFoundException(messageId: 'lms::messages.course_not_found', context: ['slug' => $slug]);
        }

        $studentId = $this->studentIdOrFail();
        $enrollment = $this->enrollments->find($studentId, $course->id());
        if ($enrollment === null || !$enrollment->status()->grantsAccess()) {
            Session::flash('error', 'برای ورود به فضای یادگیری باید در دوره ثبت‌نام کنید.');

            return redirect('/lms/courses/' . $course->slug());
        }

        $completed = [];
        foreach ($this->enrollments->progressOf($enrollment->id()) as $p) {
            if ($p->isCompleted()) {
                $completed[$p->lessonId()] = true;
            }
        }

        return View::render('lms::front.course-player', [
            'course' => $course,
            'enrollment' => $enrollment,
            'structure' => $this->courses->structureOf($course->id()),
            'completed' => $completed,
            'instructor' => $this->users->displayNameOf($course->instructorId(), 'مدرس'),
            'certificate' => $enrollment->isCompleted() ? $this->certificates->findForEnrollment($enrollment->id()) : null,
        ]);
    }

    /** نمایش درس — /lms/lessons/{id} */
    public function lesson(RequestInterface $request, string $id): mixed
    {
        $lesson = $this->courses->findLesson($id);
        if ($lesson === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.lesson_not_found', context: ['id' => $id]);
        }

        $course = $this->courses->findById($lesson->courseId());
        if ($course === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.course_not_found');
        }

        $studentId = $this->studentIdOrFail();
        $enrollment = $this->enrollments->find($studentId, $course->id());
        $hasAccess = $enrollment !== null && $enrollment->status()->grantsAccess();

        // 🔒 گارد محتوا: ثبت‌نام فعال یا درسِ پیش‌نمایش رایگان (در دورهٔ منتشرشده)
        // staff: مدرسِ همان دوره یا دارندگان lms.course.view (مدیر/دستیار)
        $isStaff = $course->instructorId() === $studentId || Gate::allows('lms.course.view');
        if (!$hasAccess && !($lesson->isFreePreview() && $course->isPublished()) && !$isStaff) {
            throw new ForbiddenException(messageId: 'lms::messages.lesson_forbidden');
        }

        if ($hasAccess) {
            $this->progress->markLessonStarted($enrollment, $lesson);
        }

        $quiz = $lesson->quizId() !== null ? $this->quizzes->findQuizById($lesson->quizId()) : null;
        $attempt = null;
        if ($quiz !== null && $enrollment !== null) {
            $attempt = $this->quizzes->latestAttempt($studentId, $quiz->id());
        }

        // پرسش‌وپاسخ درس (ماژول comment — DOC-095 §meta)
        $qa = $this->threads->build(
            $this->qaStore->forSubject($lesson->commentableType(), $lesson->id(), CommentStatus::Approved),
        );

        return View::render('lms::front.lesson', [
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'hasAccess' => $hasAccess || $isStaff,
            'structure' => $this->courses->structureOf($course->id()),
            'completed' => $enrollment !== null ? $this->completedSet($enrollment->id()) : [],
            'quiz' => $quiz,
            'attempt' => $attempt,
            'qaThread' => $qa,
            'qaEnabled' => (bool) \config('lms.qa.enabled', true),
            'canAsk' => $hasAccess || !((bool) \config('lms.qa.enrolled_only', true)),
        ]);
    }

    /** تکمیل درس — POST /lms/lessons/{id}/complete (DOC-095 §3.2). */
    public function completeLesson(RequestInterface $request, string $id): mixed
    {
        $studentId = $this->studentIdOrFail();

        $lesson = $this->courses->findLesson($id);
        if ($lesson === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.lesson_not_found', context: ['id' => $id]);
        }

        $enrollment = $this->enrollments->find($studentId, $lesson->courseId());
        if ($enrollment === null || !$enrollment->status()->grantsAccess()) {
            throw new ForbiddenException(messageId: 'lms::messages.not_enrolled');
        }

        try {
            $result = $this->progress->markLessonComplete($enrollment, $lesson);
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', $result['courseCompleted'] ? 'course_completed' : 'lesson_completed');
        Session::flash('progress_percent', $result['progressPercent']);

        return redirect('/lms/learn/' . $this->courseSlugOf($lesson->courseId()));
    }

    /** پرسش روی درس — POST /lms/lessons/{id}/questions. */
    public function askQuestion(RequestInterface $request, string $id): mixed
    {
        if (!(bool) \config('lms.qa.enabled', true)) {
            throw new ForbiddenException(messageId: 'lms::messages.qa_disabled');
        }

        $studentId = $this->studentIdOrFail();
        $user = Auth::user();

        $lesson = $this->courses->findLesson($id);
        if ($lesson === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.lesson_not_found');
        }

        $course = $this->courses->findById($lesson->courseId());
        $enrollment = $this->enrollments->find($studentId, $lesson->courseId());
        $hasAccess = $enrollment !== null && $enrollment->status()->grantsAccess();

        if ((bool) \config('lms.qa.enrolled_only', true) && !$hasAccess) {
            // staff (مدرس/assistant/admin) بدون ثبت‌نام هم پاسخ می‌دهند
            $isStaff = $course !== null && (
                $course->instructorId() === $studentId
                || Gate::allows('lms.attempt.grade')
                || Gate::allows('lms.course.view')
            );
            if (!$isStaff) {
                throw new ForbiddenException(messageId: 'lms::messages.not_enrolled');
            }
        }

        $body = \trim((string) $request->input('body', ''));
        $parentId = (string) $request->input('parent_id', '');

        if (\mb_strlen($body) < 2 || \mb_strlen($body) > 5000) {
            Session::flash('error', 'متن پرسش باید بین ۲ و ۵۰۰۰ نویسه باشد.');

            return back();
        }

        // ذخیرهٔ متن پاک (plain) — دفاع در عمق علاوه بر escape در ویو
        $body = Security::sanitize($body, 'plain');

        $candidate = Comment::submit(
            commentableType: $lesson->commentableType(),
            commentableId: $lesson->id(),
            authorName: $user !== null ? (string) $user->name : 'دانشجو',
            authorEmail: $user !== null ? \mb_strtolower((string) $user->email) : '',
            body: $body,
            parentId: $parentId !== '' ? $parentId : null,
            userId: (string) $studentId,
        );

        // پاسخ staff بدون moderation (config: qa.auto_approve_staff)
        $isStaffAuthor = $course !== null && (
            $course->instructorId() === $studentId
            || Gate::allows('lms.attempt.grade')
        );
        $status = $isStaffAuthor && (bool) \config('lms.qa.auto_approve_staff', true)
            ? CommentStatus::Approved
            : $this->moderator->decide($candidate, $this->qaStore->authorHasApprovedHistory($candidate->authorEmail));

        $comment = $candidate->withStatus($status);
        $this->qaStore->save($comment);

        Session::flash('status', $comment->status === CommentStatus::Approved ? 'question_posted' : 'question_pending');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function studentIdOrFail(): int
    {
        $user = Auth::user();
        if ($user === null) {
            throw new ForbiddenException(messageId: 'lms::messages.login_required');
        }

        return (int) $user->getAuthIdentifier();
    }

    /** @return array<string, true> lessonId → true */
    private function completedSet(string $enrollmentId): array
    {
        $completed = [];
        foreach ($this->enrollments->progressOf($enrollmentId) as $p) {
            if ($p->isCompleted()) {
                $completed[$p->lessonId()] = true;
            }
        }

        return $completed;
    }

    private function courseSlugOf(string $courseId): string
    {
        return $this->courses->findById($courseId)?->slug() ?? '';
    }
}
