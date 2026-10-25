<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * EnrollmentService — ثبت‌نام در دوره (DOC-095 §3.1).
 */

namespace Modules\Lms\Application\UseCases;

use Modules\Lms\Contracts\PaymentGatewayPort;
use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Entities\Enrollment;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Lms\Domain\Services\PrerequisiteChecker;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;

/**
 * EnrollmentService — جریان ثبت‌نام (Bounded Context: Enrollment):
 *
 * ```
 * رایگان → مستقیم active
 * پولی   → pending_payment → purchase → callback → verify(مبلغ محلی) → active
 * ```
 *
 * 🔒 idempotent: ثبت‌نام تکراری همان ردیف را برمی‌گرداند.
 * 🔒 verify همیشه با مبلغ دورهٔ محلی (نه مبلغ callback — DOC-120 §5).
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class EnrollmentService
{
    /**
     * @param array<string, mixed> $config کلید config('lms')
     */
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly CourseRepositoryInterface $courses,
        private readonly PrerequisiteChecker $prerequisites,
        private readonly PaymentGatewayPort $gateway,
        private readonly array $config = [],
    ) {
    }

    /**
     * ثبت‌نام (رایگان → قطعی؛ پولی → در انتظار پرداخت).
     *
     * @return array{enrollment: Enrollment, needsPayment: bool, course: Course}
     */
    public function enroll(int $studentId, Course $course): array
    {
        if (!$course->isPublished()) {
            throw new ValidationException(
                errors: ['course' => ['دوره هنوز منتشر نشده است.']],
                messageId: 'lms::messages.course_not_published',
            );
        }

        $existing = $this->enrollments->find($studentId, $course->id());
        if ($existing !== null) {
            if ($existing->isPendingPayment()) {
                return ['enrollment' => $existing, 'needsPayment' => true, 'course' => $course];
            }
            if ($existing->status()->grantsAccess()) {
                return ['enrollment' => $existing, 'needsPayment' => false, 'course' => $course];
            }
            // dropped → واکنش‌سازی مسیر پرداخت/رایگان در ادامه (همان ردیف)
        }

        // 🔒 پیش‌نیازها — فقط دوره‌های «تکمیل‌شده» حساب می‌شوند (DOC-095 §1.2)
        $missing = $this->prerequisites->missing(
            $course->prerequisiteIds(),
            $this->enrollments->completedCourseIdsOf($studentId),
        );
        if ($missing !== []) {
            $titles = [];
            foreach ($missing as $mid) {
                $prereq = $this->courses->findById($mid);
                $titles[] = $prereq !== null ? $prereq->title() : $mid;
            }

            throw new ValidationException(
                errors: ['prerequisites' => ['ابتدا دوره(های) پیش‌نیاز را کامل کنید: ' . \implode('، ', $titles)]],
                messageId: 'lms::messages.prerequisites_missing',
            );
        }

        if ($existing !== null && $existing->status() === \Modules\Lms\Domain\Enums\EnrollmentStatus::Dropped) {
            $paidBefore = $existing->paymentAuthority() !== null || $existing->paymentTransactionId() !== null;

            if ($course->isPaid() && !$paidBefore) {
                // پرداخت مجدد — همان ردیف (uk_enroll) به pending_payment بازمی‌گردد
                $existing->reopenForPayment();
                $this->enrollments->save($existing);

                return ['enrollment' => $existing, 'needsPayment' => true, 'course' => $course];
            }

            $existing->reactivate();
            $this->enrollments->save($existing);
            $this->flushEvents($existing, $course);

            return ['enrollment' => $existing, 'needsPayment' => false, 'course' => $course];
        }

        $enrollment = $course->isFree()
            ? Enrollment::free($studentId, $course->id())
            : Enrollment::pendingPayment($studentId, $course->id());

        if ($course->isFree()) {
            $enrollment->recordFreeEnrollment($course->title(), $course->price()->currency);
        }

        $this->enrollments->save($enrollment);

        if ($course->isFree()) {
            $this->courses->incrementStudentCount($course->id());
            $this->flushEvents($enrollment, $course);
        }

        return ['enrollment' => $enrollment, 'needsPayment' => $course->isPaid(), 'course' => $course];
    }

    /**
     * شروع پرداخت — purchase از پورت درگاه + ذخیرهٔ authority.
     *
     * @return array{authority: string, redirect_url: string}
     */
    public function startPayment(Enrollment $enrollment, Course $course, string $callbackUrl, bool $simulateFailure = false): array
    {
        if (!$enrollment->isPendingPayment()) {
            throw new ValidationException(
                errors: ['enrollment' => ['این ثبت‌نام در انتظار پرداخت نیست.']],
                messageId: 'lms::messages.not_pending_payment',
            );
        }
        if (!$course->isPaid()) {
            throw new ValidationException(
                errors: ['course' => ['دوره رایگان است.']],
                messageId: 'lms::messages.course_is_free',
            );
        }

        $result = $this->gateway->purchase(
            $course->price()->minor,
            $course->price()->currency,
            $enrollment->id(),
            $callbackUrl,
            null,
            null,
            [
                'course_id' => $course->id(),
                'enrollment_id' => $enrollment->id(),
                'simulate_failure' => $simulateFailure,
            ],
        );

        $enrollment->startPayment((string) $result['authority']);
        $this->enrollments->save($enrollment);

        return ['authority' => (string) $result['authority'], 'redirect_url' => (string) $result['redirect_url']];
    }

    /**
     * بازگشت/Callback از درگاه — verify با مبلغ محلی + فعال‌سازی.
     *
     * @param array<string, mixed> $callbackMeta
     *
     * @return array{success: bool, enrollment: ?Enrollment, course: ?Course, reason?: string}
     */
    public function handleCallback(string $authority, array $callbackMeta = []): array
    {
        $enrollment = $this->enrollments->findByAuthority($authority);
        if ($enrollment === null) {
            return ['success' => false, 'enrollment' => null, 'course' => null, 'reason' => 'unknown_authority'];
        }

        $course = $this->courses->findById($enrollment->courseId());
        if ($course === null) {
            return ['success' => false, 'enrollment' => $enrollment, 'course' => null, 'reason' => 'course_missing'];
        }

        // idempotency: اگر پیش‌تر فعال شده، اثر تکراری ندارد (دو callback = یک اثر)
        if (!$enrollment->isPendingPayment()) {
            return [
                'success' => $enrollment->status()->grantsAccess(),
                'enrollment' => $enrollment,
                'course' => $course,
                'reason' => 'already_processed',
            ];
        }

        // 🔒 verify از API درگاه با مبلغ/ارز محلی — نه اعتماد به status callback
        $verified = false;
        try {
            $verified = $this->gateway->verify($authority, $course->price()->minor, $course->price()->currency, $callbackMeta);
        } catch (\Throwable) {
            $verified = false;
        }

        if (!$verified) {
            return ['success' => false, 'enrollment' => $enrollment, 'course' => $course, 'reason' => 'verify_failed'];
        }

        $transactionId = isset($callbackMeta['transaction_id']) ? (string) $callbackMeta['transaction_id'] : null;
        if ($transactionId !== null && \strlen($transactionId) > 26) {
            $transactionId = \substr($transactionId, 0, 26);
        }

        $enrollment->activateAfterPayment($course->title(), $course->price()->minor, $course->price()->currency, $transactionId);
        $this->enrollments->save($enrollment);

        $this->courses->incrementStudentCount($course->id());
        $this->flushEvents($enrollment, $course);

        return ['success' => true, 'enrollment' => $enrollment, 'course' => $course];
    }

    /** انصراف از دوره (دانشجو/مدیر). */
    public function drop(Enrollment $enrollment): Enrollment
    {
        $enrollment->drop();
        $this->enrollments->save($enrollment);

        $this->courses->incrementStudentCount($enrollment->courseId(), -1);

        return $enrollment;
    }

    private function flushEvents(Enrollment $enrollment, Course $course): void
    {
        foreach ($enrollment->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
