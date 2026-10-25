<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * LessonProgress — پیشرفت یک درس برای یک ثبت‌نام (DOC-095 §2/§3.2).
 */

namespace Modules\Lms\Domain\Entities;

use Modules\Lms\Domain\Enums\ProgressStatus;

/**
 * @api 1.0
 */
final class LessonProgress
{
    private function __construct(
        private readonly int $id,
        private readonly string $enrollmentId,
        private readonly string $lessonId,
        private ProgressStatus $status,
        private ?\DateTimeImmutable $completedAt,
    ) {
    }

    public static function start(string $enrollmentId, string $lessonId): self
    {
        return new self(0, $enrollmentId, $lessonId, ProgressStatus::InProgress, null);
    }

    public static function completed(string $enrollmentId, string $lessonId, ?\DateTimeImmutable $at = null): self
    {
        return new self(
            0,
            $enrollmentId,
            $lessonId,
            ProgressStatus::Completed,
            $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['enrollment_id'] ?? ''),
            (string) ($row['lesson_id'] ?? ''),
            ProgressStatus::tryFrom((string) ($row['status'] ?? 'not_started')) ?? ProgressStatus::NotStarted,
            self::date($row['completed_at'] ?? null),
        );
    }

    /** درس شروع شد (بدون بازگشت از completed). */
    public function markInProgress(): void
    {
        if ($this->status === ProgressStatus::Completed) {
            return;
        }
        $this->status = ProgressStatus::InProgress;
    }

    /** درس تکمیل شد — idempotent. */
    public function markCompleted(?\DateTimeImmutable $at = null): void
    {
        if ($this->status === ProgressStatus::Completed) {
            return;
        }
        $this->status = ProgressStatus::Completed;
        $this->completedAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function id(): int
    {
        return $this->id;
    }

    public function enrollmentId(): string
    {
        return $this->enrollmentId;
    }

    public function lessonId(): string
    {
        return $this->lessonId;
    }

    public function status(): ProgressStatus
    {
        return $this->status;
    }

    public function isCompleted(): bool
    {
        return $this->status === ProgressStatus::Completed;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'enrollment_id' => $this->enrollmentId,
            'lesson_id' => $this->lessonId,
            'status' => $this->status->value,
            'completed_at' => self::fmt($this->completedAt),
        ];
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
