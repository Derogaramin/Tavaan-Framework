<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * Quiz — آزمون (DOC-095 §1.2: Quiz → Question, Option, Attempt).
 */

namespace Modules\Lms\Domain\Entities;

/**
 * Quiz — time_limit/pass_score(درصدی)/max_attempts.
 *
 * @api 1.0
 */
final class Quiz
{
    private function __construct(
        private readonly string $id,
        private readonly ?string $lessonId,
        private readonly string $courseId,
        private string $title,
        private ?int $timeLimit,
        private int $passScore,
        private int $maxAttempts,
    ) {
    }

    public static function create(
        string $courseId,
        string $title,
        ?string $lessonId = null,
        ?int $timeLimit = null,
        int $passScore = 50,
        int $maxAttempts = 1,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان آزمون نمی‌تواند خالی باشد.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $lessonId,
            $courseId,
            $title,
            $timeLimit === null ? null : \max(1, $timeLimit),
            \max(0, \min(100, $passScore)),
            \max(1, $maxAttempts),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            isset($row['lesson_id']) && $row['lesson_id'] !== null && $row['lesson_id'] !== '' ? (string) $row['lesson_id'] : null,
            (string) ($row['course_id'] ?? ''),
            (string) ($row['title'] ?? ''),
            isset($row['time_limit']) && $row['time_limit'] !== null ? (int) $row['time_limit'] : null,
            (int) ($row['pass_score'] ?? 50),
            (int) ($row['max_attempts'] ?? 1),
        );
    }

    public function revise(string $title, ?int $timeLimit = null, ?int $passScore = null, ?int $maxAttempts = null, bool $clearTimeLimit = false): void
    {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان آزمون نمی‌تواند خالی باشد.');
        }
        $this->title = $title;
        $this->timeLimit = $clearTimeLimit ? null : ($timeLimit === null ? $this->timeLimit : \max(1, $timeLimit));
        $this->passScore = $passScore === null ? $this->passScore : \max(0, \min(100, $passScore));
        $this->maxAttempts = $maxAttempts === null ? $this->maxAttempts : \max(1, $maxAttempts);
    }

    /** آیا یک تلاشِ شروع‌شده هنوز در پنجرهٔ زمانی است؟ (DOC-095 §3.3) */
    public function isWithinTimeLimit(\DateTimeImmutable $startedAt, ?\DateTimeImmutable $now = null): bool
    {
        if ($this->timeLimit === null) {
            return true;
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $deadline = $startedAt->add(new \DateInterval('PT' . $this->timeLimit . 'M'));

        return $now <= $deadline;
    }

    public function deadlineOf(\DateTimeImmutable $startedAt): ?\DateTimeImmutable
    {
        return $this->timeLimit === null ? null : $startedAt->add(new \DateInterval('PT' . $this->timeLimit . 'M'));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function lessonId(): ?string
    {
        return $this->lessonId;
    }

    public function courseId(): string
    {
        return $this->courseId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function timeLimit(): ?int
    {
        return $this->timeLimit;
    }

    public function passScore(): int
    {
        return $this->passScore;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lessonId,
            'course_id' => $this->courseId,
            'title' => $this->title,
            'time_limit' => $this->timeLimit,
            'pass_score' => $this->passScore,
            'max_attempts' => $this->maxAttempts,
        ];
    }
}
