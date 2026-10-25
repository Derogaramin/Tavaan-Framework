<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CertificateWasIssued — گواهی صادر شد (DOC-095 §3.4/§5).
 */

namespace Modules\Lms\Domain\Events;

/**
 * مصرف‌کننده: notification (email با لینک گواهی)، report.
 *
 * @api 1.0
 */
final readonly class CertificateWasIssued
{
    public function __construct(
        public string $certificateId,
        public string $serial,
        public int $studentId,
        public string $courseId,
        public string $courseTitle,
        public string $enrollmentId,
        public string $verifyUrl,
        public ?string $workflowInstanceId = null,
    ) {
    }

    public function eventName(): string
    {
        return 'lms.certificate.issued';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'certificate_id' => $this->certificateId,
            'serial' => $this->serial,
            'student_id' => $this->studentId,
            'course_id' => $this->courseId,
            'course_title' => $this->courseTitle,
            'enrollment_id' => $this->enrollmentId,
            'verify_url' => $this->verifyUrl,
            'workflow_instance_id' => $this->workflowInstanceId,
        ];
    }
}
