<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * Enrollment — ثبت‌نام دانشجو در دوره (DOC-095 §1.2/§3.1).
 */

namespace Modules\Lms\Domain\Entities;

use Modules\Lms\Domain\Enums\EnrollmentStatus;
use Modules\Lms\Domain\Events\CourseWasCompleted;
use Modules\Lms\Domain\Events\StudentWasEnrolled;

/**
 * Enrollment — ریشهٔ Aggregate ثبت‌نام (Student, Course, Progress, Status).
 *
 * invariantها:
 * - انتقال وضعیت فقط طبق ماتریس EnrollmentStatus
 * - progress_percent ∈ [0, 100]
 * - completed_at فقط با وضعیت completed
 * - یکتایی (student, course) در سطح DB (uk_lms_enroll)
 *
 * @api 1.0
 */
final class Enrollment
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private readonly int $studentId,
        private readonly string $courseId,
        private EnrollmentStatus $status,
        private float $progressPercent,
        private ?\DateTimeImmutable $enrolledAt,
        private ?\DateTimeImmutable $completedAt,
        private ?string $paymentTransactionId,
        private ?string $paymentAuthority,
        private readonly ?\DateTimeImmutable $createdAt,
    ) {
    }

    /** ثبت‌نام دورهٔ رایگان — مستقیماً active (DOC-095 §3.1). */
    public static function free(int $studentId, string $courseId, ?\DateTimeImmutable $at = null): self
    {
        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $studentId,
            $courseId,
            EnrollmentStatus::Active,
            0.0,
            $now,
            null,
            null,
            null,
            $now,
        );
    }

    /** ثبت‌نام دورهٔ پولی — pending_payment تا verify درگاه (DOC-095 §3.1). */
    public static function pendingPayment(int $studentId, string $courseId, ?\DateTimeImmutable $at = null): self
    {
        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $studentId,
            $courseId,
            EnrollmentStatus::PendingPayment,
            0.0,
            null,
            null,
            null,
            null,
            $now,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (int) ($row['student_id'] ?? 0),
            (string) ($row['course_id'] ?? ''),
            EnrollmentStatus::tryFrom((string) ($row['status'] ?? 'active')) ?? EnrollmentStatus::Active,
            (float) ($row['progress_percent'] ?? 0),
            self::date($row['enrolled_at'] ?? null),
            self::date($row['completed_at'] ?? null),
            isset($row['payment_transaction_id']) && $row['payment_transaction_id'] !== null ? (string) $row['payment_transaction_id'] : null,
            isset($row['payment_authority']) && $row['payment_authority'] !== null && $row['payment_authority'] !== '' ? (string) $row['payment_authority'] : null,
            self::date($row['created_at'] ?? null),
        );
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    /** پرداخت تأیید شد → active + رویداد StudentWasEnrolled (DOC-095 §3.1). */
    public function activateAfterPayment(string $courseTitle, int $priceMinor, string $currency, ?string $transactionId = null): void
    {
        $this->transitionTo(EnrollmentStatus::Active);
        $this->enrolledAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->paymentTransactionId = $transactionId;

        $this->record(new StudentWasEnrolled($this->id, $this->studentId, $this->courseId, $courseTitle, true, $priceMinor, $currency));
    }

    /** ثبت‌نام رایگان — رویداد StudentWasEnrolled (بدون پرداخت). */
    public function recordFreeEnrollment(string $courseTitle, string $currency): void
    {
        $this->record(new StudentWasEnrolled($this->id, $this->studentId, $this->courseId, $courseTitle, false, 0, $currency));
    }

    /** شروع پرداخت — authority درگاه برای callback/verify ذخیره می‌شود. */
    public function startPayment(string $authority): void
    {
        if ($this->status !== EnrollmentStatus::PendingPayment) {
            throw new \DomainException('فقط ثبت‌نام در انتظار پرداخت، پرداخت مجدد می‌گیرد.');
        }
        $this->paymentAuthority = $authority;
    }

    /** همهٔ درس‌ها تمام شد → completed (DOC-095 §3.2). */
    public function complete(string $courseTitle): void
    {
        $this->transitionTo(EnrollmentStatus::Completed);
        $this->progressPercent = 100.0;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->record(new CourseWasCompleted($this->id, $this->studentId, $this->courseId, $courseTitle));
    }

    /** انصراف دانشجو / مدیر. */
    public function drop(): void
    {
        $this->transitionTo(EnrollmentStatus::Dropped);
    }

    /** بازگشت مدیرتی از dropped. */
    public function reactivate(): void
    {
        $this->transitionTo(EnrollmentStatus::Active);
    }

    /** دورهٔ پولیِ رهاشده — بازگشت به چرخهٔ پرداخت (بدون پرداخت پیشین). */
    public function reopenForPayment(): void
    {
        $this->transitionTo(EnrollmentStatus::PendingPayment);
        $this->paymentAuthority = null;
    }

    /** به‌روزرسانی درصد پیشرفت (ProgressCalculator — DOC-095 §3.2). */
    public function updateProgress(float $percent): void
    {
        $this->progressPercent = \round(\max(0.0, \min(100.0, $percent)), 2);
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function courseId(): string
    {
        return $this->courseId;
    }

    public function status(): EnrollmentStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status->grantsAccess();
    }

    public function isCompleted(): bool
    {
        return $this->status === EnrollmentStatus::Completed;
    }

    public function isPendingPayment(): bool
    {
        return $this->status === EnrollmentStatus::PendingPayment;
    }

    public function progressPercent(): float
    {
        return $this->progressPercent;
    }

    public function enrolledAt(): ?\DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function paymentTransactionId(): ?string
    {
        return $this->paymentTransactionId;
    }

    public function paymentAuthority(): ?string
    {
        return $this->paymentAuthority;
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
            'student_id' => $this->studentId,
            'course_id' => $this->courseId,
            'status' => $this->status->value,
            'progress_percent' => $this->progressPercent,
            'enrolled_at' => self::fmt($this->enrolledAt),
            'completed_at' => self::fmt($this->completedAt),
            'payment_transaction_id' => $this->paymentTransactionId,
            'payment_authority' => $this->paymentAuthority,
            'created_at' => self::fmt($this->createdAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function transitionTo(EnrollmentStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت ثبت‌نام نامجاز: %s → %s (ثبت‌نام %s)',
                $this->status->value,
                $target->value,
                $this->id,
            ));
        }

        $this->status = $target;
    }

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
