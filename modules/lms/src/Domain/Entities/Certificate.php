<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * Certificate — گواهی پایان دوره (DOC-095 §1.2/§3.4).
 */

namespace Modules\Lms\Domain\Entities;

use Modules\Lms\Domain\Enums\CertificateStatus;
use Modules\Lms\Domain\Events\CertificateWasIssued;

/**
 * Certificate — serial یکتا + verify_url عمومی (AC-LMS-08).
 *
 * @api 1.0
 */
final class Certificate
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private readonly ?int $templateId,
        private readonly string $enrollmentId,
        private readonly int $studentId,
        private readonly string $courseId,
        private readonly string $serial,
        private CertificateStatus $status,
        private readonly \DateTimeImmutable $issuedAt,
        private readonly string $verifyUrl,
        private ?string $filePath,
        private ?string $workflowInstanceId,
    ) {
    }

    public static function issue(
        string $enrollmentId,
        int $studentId,
        string $courseId,
        string $courseTitle,
        string $serial,
        string $verifyUrl,
        ?int $templateId = null,
        ?string $filePath = null,
        ?string $workflowInstanceId = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($serial) === '') {
            throw new \DomainException('گواهی بدون serial صادر نمی‌شود.');
        }

        $certificate = new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $templateId,
            $enrollmentId,
            $studentId,
            $courseId,
            $serial,
            CertificateStatus::Issued,
            $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            $verifyUrl,
            $filePath,
            $workflowInstanceId,
        );

        $certificate->record(new CertificateWasIssued(
            $certificate->id,
            $serial,
            $studentId,
            $courseId,
            $courseTitle,
            $enrollmentId,
            $verifyUrl,
            $workflowInstanceId,
        ));

        return $certificate;
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            isset($row['template_id']) && $row['template_id'] !== null ? (int) $row['template_id'] : null,
            (string) ($row['enrollment_id'] ?? ''),
            (int) ($row['student_id'] ?? 0),
            (string) ($row['course_id'] ?? ''),
            (string) ($row['serial'] ?? ''),
            CertificateStatus::tryFrom((string) ($row['status'] ?? 'issued')) ?? CertificateStatus::Issued,
            self::date($row['issued_at'] ?? null) ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            (string) ($row['verify_url'] ?? ''),
            isset($row['file_path']) && $row['file_path'] !== null && $row['file_path'] !== '' ? (string) $row['file_path'] : null,
            isset($row['workflow_instance_id']) && $row['workflow_instance_id'] !== null && $row['workflow_instance_id'] !== '' ? (string) $row['workflow_instance_id'] : null,
        );
    }

    /** ابطال مدیرتی (verify عمومی «باطل‌شده» نشان می‌دهد). */
    public function revoke(): void
    {
        if ($this->status === CertificateStatus::Revoked) {
            return;
        }
        $this->status = CertificateStatus::Revoked;
    }

    public function attachFile(string $path): void
    {
        $this->filePath = $path;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function templateId(): ?int
    {
        return $this->templateId;
    }

    public function enrollmentId(): string
    {
        return $this->enrollmentId;
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function courseId(): string
    {
        return $this->courseId;
    }

    public function serial(): string
    {
        return $this->serial;
    }

    public function status(): CertificateStatus
    {
        return $this->status;
    }

    public function isValid(): bool
    {
        return $this->status->isValid();
    }

    public function issuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function verifyUrl(): string
    {
        return $this->verifyUrl;
    }

    public function filePath(): ?string
    {
        return $this->filePath;
    }

    public function workflowInstanceId(): ?string
    {
        return $this->workflowInstanceId;
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->templateId,
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'course_id' => $this->courseId,
            'serial' => $this->serial,
            'status' => $this->status->value,
            'issued_at' => self::fmt($this->issuedAt),
            'verify_url' => $this->verifyUrl,
            'file_path' => $this->filePath,
            'workflow_instance_id' => $this->workflowInstanceId,
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    private static function fmt(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
