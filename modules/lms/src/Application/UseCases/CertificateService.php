<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CertificateService — صدور/راستی‌آزمایی گواهی (DOC-095 §3.4 — AC-LMS-08).
 */

namespace Modules\Lms\Application\UseCases;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Lms\Domain\Entities\Certificate;
use Modules\Lms\Domain\Entities\Enrollment;
use Modules\Lms\Domain\Repositories\CertificateRepositoryInterface;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Modules\Lms\Domain\Services\CertificateSerialGenerator;
use Modules\Lms\Infrastructure\Workflow\CertificateWorkflow;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Storage;
use Tavaan\Core\Facade\Facades\View;

/**
 * CertificateService — جریان §3.4 با Workflow:
 *
 * ```
 * enrollment completed + همهٔ آزمون‌ها pass
 *   → Workflow(lms-issue-certificate).start
 *   → generate: serial یکتا + سند HTML آمادهٔ چاپ (report-engine-approach)
 *   → ذخیره در storage + رکورد گواهی (verify_url)
 *   → issue: رویداد lms.certificate.issued → notification
 * ```
 *
 * 📄 سند گواهی: HTML آمادهٔ چاپ (print-to-PDF) — همان رویکرد PdfExporter
 * موتور report با driver=html (بدون ext؛ RTL/فارسی کامل). انحراف مستند
 * در گزارش M9 گام ۵.
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class CertificateService
{
    /**
     * @param array<string, mixed> $config کلید config('lms.certificates')
     */
    public function __construct(
        private readonly CertificateRepositoryInterface $certificates,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly CourseRepositoryInterface $courses,
        private readonly QuizRepositoryInterface $quizzes,
        private readonly UserReaderInterface $users,
        private readonly CertificateSerialGenerator $serials,
        private readonly CertificateWorkflow $workflow,
        private readonly array $config = [],
    ) {
    }

    /**
     * صدور گواهی برای ثبت‌نامِ تکمیل‌شده (idempotent).
     *
     * شرایط (§3.4): enrollment completed + همهٔ آزمون‌های دوره pass.
     */
    public function issueForEnrollment(Enrollment $enrollment, bool $force = false): ?Certificate
    {
        // idempotency — گواهی صادرشدهٔ معتبر تکرار نمی‌شود
        $existing = $this->certificates->findForEnrollment($enrollment->id());
        if ($existing !== null && $existing->isValid()) {
            return $existing;
        }

        $course = $this->courses->findById($enrollment->courseId());
        if ($course === null) {
            return null;
        }

        if (!$force) {
            if (!$enrollment->isCompleted()) {
                return null;
            }
            $pass = $this->quizzes->passStatusForStudentInCourse($enrollment->studentId(), $course->id());
            if ($pass['total'] > 0 && $pass['passed'] < $pass['total']) {
                return null;   // آزمونِ قبول‌نشده باقی است
            }
        }

        // Workflow — ثبت نمونه + گذار generate (audit/visualization)
        $instanceId = $this->workflow->start($enrollment->id(), [
            'course_id' => $course->id(),
            'student_id' => $enrollment->studentId(),
        ]);
        if ($instanceId !== null) {
            $this->workflow->markGenerating($instanceId);
        }

        try {
            $serial = $this->generateSerial();
            $verifyUrl = \rtrim((string) ($this->config['verify_base'] ?? '/lms/certificates'), '/') . '/' . $serial;
            $studentName = $this->users->displayNameOf($enrollment->studentId(), 'دانشجو');

            $certificate = Certificate::issue(
                enrollmentId: $enrollment->id(),
                studentId: $enrollment->studentId(),
                courseId: $course->id(),
                courseTitle: $course->title(),
                serial: $serial,
                verifyUrl: $verifyUrl,
                filePath: null,
                workflowInstanceId: $instanceId,
            );

            // سند آمادهٔ چاپ — best-effort (گواهی بدون فایل هم معتبر است)
            $filePath = $this->renderDocument($certificate, $course->title(), $studentName);
            if ($filePath !== null) {
                $certificate->attachFile($filePath);
            }

            $this->certificates->save($certificate);

            if ($instanceId !== null) {
                $this->workflow->markIssued($instanceId);
            }

            foreach ($certificate->pullEvents() as $event) {
                if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                    Event::dispatch($event->eventName(), $event->toPayload());
                }
            }

            return $certificate;
        } catch (\Throwable) {
            if ($instanceId !== null) {
                $this->workflow->markFailed($instanceId);
            }

            throw new ValidationException(
                errors: ['certificate' => ['صدور گواهی ناموفق بود.']],
                messageId: 'lms::messages.certificate_issue_failed',
            );
        }
    }

    /**
     * راستی‌آزمایی عمومی با serial (AC-LMS-08: verify عمومی).
     *
     * @return array{valid: bool, certificate: ?Certificate, courseTitle: string, studentName: string, reason?: string}
     */
    public function verify(string $serial): array
    {
        $serial = \trim($serial);

        // 🔒 پیش‌فیلتر قالب + رقم کنترل — serial بی‌ربط بدون تماس با DB رد می‌شود
        if (!$this->serials->isValidFormat($serial)) {
            return ['valid' => false, 'certificate' => null, 'courseTitle' => '', 'studentName' => '', 'reason' => 'bad_format'];
        }

        $certificate = $this->certificates->findBySerial($serial);
        if ($certificate === null) {
            return ['valid' => false, 'certificate' => null, 'courseTitle' => '', 'studentName' => '', 'reason' => 'not_found'];
        }

        $course = $this->courses->findById($certificate->courseId());

        return [
            'valid' => $certificate->isValid(),
            'certificate' => $certificate,
            'courseTitle' => $course?->title() ?? '',
            'studentName' => $this->users->displayNameOf($certificate->studentId(), 'دانشجو'),
            'reason' => $certificate->isValid() ? null : 'revoked',
        ];
    }

    /** ابطال مدیرتی گواهی. */
    public function revoke(Certificate $certificate): Certificate
    {
        $certificate->revoke();
        $this->certificates->save($certificate);

        return $certificate;
    }

    /** محتوای سند گواهی (برای چاپ/دانلود عمومی). */
    public function documentHtml(Certificate $certificate): string
    {
        $course = $this->courses->findById($certificate->courseId());
        $studentName = $this->users->displayNameOf($certificate->studentId(), 'دانشجو');

        return (string) View::render('lms::front.certificate-view', [
            'certificate' => $certificate,
            'courseTitle' => $course?->title() ?? '',
            'studentName' => $studentName,
            'instructorName' => $course !== null ? $this->users->displayNameOf($course->instructorId(), 'مدرس') : '',
        ]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function generateSerial(): string
    {
        do {
            $serial = $this->serials->generate();
        } while ($this->certificates->serialExists($serial));

        return $serial;
    }

    /** رندر سند HTML + ذخیرهٔ snapshot روی disk — best-effort. */
    private function renderDocument(Certificate $certificate, string $courseTitle, string $studentName): ?string
    {
        try {
            $course = $this->courses->findById($certificate->courseId());
            $html = (string) View::render('lms::front.certificate-view', [
                'certificate' => $certificate,
                'courseTitle' => $courseTitle,
                'studentName' => $studentName,
                'instructorName' => $course !== null ? $this->users->displayNameOf($course->instructorId(), 'مدرس') : '',
            ]);

            $dir = \trim((string) ($this->config['storage_path'] ?? 'certificates'), '/');
            $path = $dir . '/' . $certificate->serial() . '.html';

            return Storage::put($path, $html) ? $path : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
