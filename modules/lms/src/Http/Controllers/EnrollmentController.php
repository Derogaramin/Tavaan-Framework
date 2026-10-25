<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * EnrollmentController — ثبت‌نام + پرداخت (DOC-095 §3.1).
 */

namespace Modules\Lms\Http\Controllers;

use Modules\Lms\Application\UseCases\EnrollmentService;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * مسیرها (DOC-095 §3.1):
 * - POST /lms/courses/{id}/enroll
 * - GET  /lms/pay/{id} + /lms/pay/sandbox/{authority} + /lms/pay/callback
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class EnrollmentController
{
    public function __construct(
        private readonly EnrollmentService $service,
        private readonly CourseRepositoryInterface $courses,
        private readonly EnrollmentRepositoryInterface $enrollments,
    ) {
    }

    /** ثبت‌نام — رایگان: مستقیم؛ پولی: redirect به پرداخت. */
    public function enroll(RequestInterface $request, string $id): mixed
    {
        $studentId = $this->studentIdOrFail();

        $course = $this->courses->findById($id);
        if ($course === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.course_not_found', context: ['id' => $id]);
        }

        try {
            $result = $this->service->enroll($studentId, $course);
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        if ($result['needsPayment']) {
            return redirect('/lms/pay/' . $result['enrollment']->id());
        }

        Session::flash('status', 'enrolled');

        return redirect('/lms/learn/' . $course->slug());
    }

    /** شروع پرداخت ثبت‌نام — GET /lms/pay/{id}. */
    public function pay(RequestInterface $request, string $id): mixed
    {
        $studentId = $this->studentIdOrFail();

        $enrollment = $this->enrollments->findById($id);
        if ($enrollment === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.enrollment_not_found', context: ['id' => $id]);
        }
        if ($enrollment->studentId() !== $studentId) {
            throw new ForbiddenException(messageId: 'lms::messages.enrollment_forbidden');
        }

        $course = $this->courses->findById($enrollment->courseId());
        if ($course === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.course_not_found');
        }

        if (!$enrollment->isPendingPayment()) {
            return redirect('/lms/learn/' . $course->slug());
        }

        try {
            $result = $this->service->startPayment(
                $enrollment,
                $course,
                $this->absoluteUrl((string) \config('lms.payment.callback_url', '/lms/pay/callback')),
                simulateFailure: $request->input('simulate_failure') !== null,
            );
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        return redirect((string) $result['redirect_url']);
    }

    /** صفحهٔ درگاه آزمایشی (sandbox) — GET /lms/pay/sandbox/{authority}. */
    public function sandboxPage(RequestInterface $request, string $authority): mixed
    {
        $enrollment = $this->enrollments->findByAuthority($authority);
        $course = $enrollment !== null ? $this->courses->findById($enrollment->courseId()) : null;

        return View::render('lms::front.pay-sandbox', [
            'authority' => $authority,
            'enrollment' => $enrollment,
            'course' => $course,
        ]);
    }

    /** بازگشت از درگاه — GET /lms/pay/callback?authority=&status=. */
    public function callback(RequestInterface $request): mixed
    {
        $authority = (string) $request->input('authority', '');
        if ($authority === '') {
            throw new RouteNotFoundException(messageId: 'lms::messages.enrollment_not_found');
        }

        $result = $this->service->handleCallback($authority, [
            'status' => (string) $request->input('status', ''),
            'transaction_id' => $request->input('transaction_id'),
        ]);

        return View::render('lms::front.payment-result', [
            'success' => (bool) $result['success'],
            'enrollment' => $result['enrollment'],
            'course' => $result['course'],
            'message' => match ($result['reason'] ?? '') {
                'verify_failed' => 'پرداخت توسط درگاه تأیید نشد.',
                'unknown_authority' => 'شناسهٔ پرداخت ناشناخته است.',
                'already_processed' => 'این پرداخت پیش‌تر پردازش شده است.',
                default => '',
            },
        ]);
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

    private function absoluteUrl(string $path): string
    {
        $base = \rtrim((string) \config('app.url', ''), '/');

        return $base . $path;
    }
}
