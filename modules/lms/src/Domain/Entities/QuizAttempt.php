<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * QuizAttempt — یک تلاش آزمون (DOC-095 §3.3).
 */

namespace Modules\Lms\Domain\Entities;

use Modules\Lms\Domain\Enums\AttemptStatus;
use Modules\Lms\Domain\Events\AttemptWasGraded;
use Modules\Lms\Domain\Events\QuizWasPassed;

/**
 * QuizAttempt — چرخه: in_progress → submitted → graded.
 *
 * ساختار answers (ستون JSON):
 * ```
 * {
 *   "answers":   {"<questionId>": "پاسخ دانشجو"},
 *   "auto":      {"<questionId>": {"score": n, "correct": bool}},   ← خودکارها
 *   "grades":    {"<questionId>": {"score": n, "note": "…"}},       ← دستی‌ها
 *   "auto_score": int, "max_score": int
 * }
 * ```
 *
 * 🔒 نمرهٔ نهایی = auto_score + Σ grades — هرگز float (ADR-0013 در مالی؛
 * نمره هم int قطعی است). pass_score درصدی از max_score.
 *
 * @api 1.0
 */
final class QuizAttempt
{
    /** @var list<object> */
    private array $recordedEvents = [];

    /** @var array<string, string> questionId → پاسخ دانشجو */
    private array $answers = [];

    /** @var array<string, array{score: int, correct: bool}> نتایج خودکار */
    private array $autoGrades = [];

    /** @var array<string, array{score: int, note: string}> نتایج دستی */
    private array $manualGrades = [];

    private int $maxScore = 0;

    private function __construct(
        private readonly string $id,
        private readonly string $quizId,
        private readonly int $studentId,
        private AttemptStatus $status,
        private int $score,
        private ?bool $passed,
        private readonly \DateTimeImmutable $startedAt,
        private ?\DateTimeImmutable $submittedAt,
        private ?int $gradedBy,
        private ?\DateTimeImmutable $gradedAt,
    ) {
    }

    public static function start(string $quizId, int $studentId, ?\DateTimeImmutable $at = null): self
    {
        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $quizId,
            $studentId,
            AttemptStatus::InProgress,
            0,
            null,
            $now,
            null,
            null,
            null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = [];
        $raw = $row['answers'] ?? null;
        if (\is_string($raw) && $raw !== '') {
            $decoded = \json_decode($raw, true);
            $data = \is_array($decoded) ? $decoded : [];
        } elseif (\is_array($raw)) {
            $data = $raw;
        }

        $attempt = new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['quiz_id'] ?? ''),
            (int) ($row['student_id'] ?? 0),
            AttemptStatus::tryFrom((string) ($row['status'] ?? 'in_progress')) ?? AttemptStatus::InProgress,
            (int) ($row['score'] ?? 0),
            isset($row['passed']) && $row['passed'] !== null ? (bool) $row['passed'] : null,
            self::date($row['started_at'] ?? null) ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            self::date($row['submitted_at'] ?? null),
            isset($row['graded_by']) && $row['graded_by'] !== null ? (int) $row['graded_by'] : null,
            self::date($row['graded_at'] ?? null),
        );

        $attempt->answers = \is_array($data['answers'] ?? null) ? $data['answers'] : [];
        $attempt->autoGrades = \is_array($data['auto'] ?? null) ? $data['auto'] : [];
        $attempt->manualGrades = \is_array($data['grades'] ?? null) ? $data['grades'] : [];
        $attempt->maxScore = (int) ($data['max_score'] ?? 0);

        return $attempt;
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    /** ثبت پاسخ یک سؤال (فقط در جریان آزمون). */
    public function answer(string $questionId, string $value): void
    {
        if ($this->status !== AttemptStatus::InProgress) {
            throw new \DomainException('پس از ثبت نهایی، پاسخ‌ها تغییر نمی‌کنند.');
        }
        $this->answers[$questionId] = $value;
    }

    /**
     * ثبت پاسخ‌های ارسالی یکجا (فرم آزمون).
     *
     * @param array<string, string> $answers
     */
    public function fillAnswers(array $answers): void
    {
        foreach ($answers as $questionId => $value) {
            $this->answer((string) $questionId, (string) $value);
        }
    }

    /**
     * ثبت نهایی — نتیجهٔ نمره‌دهی خودکار (QuizGrader) داده می‌شود.
     *
     * @param array<string, array{score: int, correct: bool}> $autoGrades
     */
    public function submit(array $autoGrades, int $autoScore, int $maxScore, bool $hasManual, bool $passedIfNoManual, int $passScorePercent, ?string $courseId = null, ?\DateTimeImmutable $at = null): void
    {
        if ($this->status !== AttemptStatus::InProgress) {
            throw new \DomainException('این تلاش پیش‌تر ثبت نهایی شده است.');
        }

        $this->autoGrades = $autoGrades;
        $this->maxScore = $maxScore;
        $this->submittedAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($hasManual) {
            // منتظر نمره‌دهی دستی — score فعلی فقط خودکارها
            $this->status = AttemptStatus::Submitted;
            $this->score = $autoScore;
            $this->passed = null;

            return;
        }

        $this->status = AttemptStatus::Graded;
        $this->score = $autoScore;
        $this->passed = $passedIfNoManual;
        $this->gradedAt = $this->submittedAt;

        if ($this->passed && $courseId !== null) {
            $this->record(new QuizWasPassed($this->id, $this->quizId, $this->studentId, $courseId, $this->score, $maxScore, $passScorePercent));
        }
    }

    /**
     * نمرهٔ دستی یک سؤال (مدرس/assistant — DOC-095 §3.3).
     */
    public function gradeQuestion(string $questionId, int $score, int $questionMaxScore, string $note = ''): void
    {
        if ($this->status !== AttemptStatus::Submitted) {
            throw new \DomainException('فقط تلاشِ ثبت‌شدهٔ در انتظار نمره، دستی نمره می‌گیرد.');
        }
        if ($score < 0 || $score > $questionMaxScore) {
            throw new \DomainException(\sprintf('نمرهٔ سؤال باید بین ۰ و %d باشد.', $questionMaxScore));
        }

        $this->manualGrades[$questionId] = ['score' => $score, 'note' => $note];
    }

    /**
     * پایان نمره‌دهی دستی — محاسبهٔ نمرهٔ نهایی + pass/fail + رویدادها.
     *
     * @param list<string> $manualQuestionIds سؤال‌های دستیِ آزمون (بررسی کامل بودن)
     */
    public function finalizeGrading(array $manualQuestionIds, int $gradedBy, bool $passWhenThreshold, string $courseId, int $passScorePercent, ?\DateTimeImmutable $at = null): void
    {
        if ($this->status !== AttemptStatus::Submitted) {
            throw new \DomainException('این تلاش در وضعیت نمره‌دهی دستی نیست.');
        }

        $missing = \array_diff($manualQuestionIds, \array_keys($this->manualGrades));
        if ($missing !== []) {
            throw new \DomainException('نمرهٔ همهٔ سؤال‌های دستی ثبت نشده است.');
        }

        $manualSum = 0;
        foreach ($this->manualGrades as $grade) {
            $manualSum += (int) ($grade['score'] ?? 0);
        }

        $this->score = $this->autoScore() + $manualSum;
        $this->passed = $passWhenThreshold;
        $this->status = AttemptStatus::Graded;
        $this->gradedBy = $gradedBy;
        $this->gradedAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->record(new AttemptWasGraded($this->id, $this->quizId, $this->studentId, $courseId, $this->score, $this->maxScore, $this->passed, $gradedBy));

        if ($this->passed) {
            $this->record(new QuizWasPassed($this->id, $this->quizId, $this->studentId, $courseId, $this->score, $this->maxScore, $passScorePercent));
        }
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function quizId(): string
    {
        return $this->quizId;
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function status(): AttemptStatus
    {
        return $this->status;
    }

    public function isGraded(): bool
    {
        return $this->status === AttemptStatus::Graded;
    }

    public function isAwaitingManualGrading(): bool
    {
        return $this->status === AttemptStatus::Submitted;
    }

    public function score(): int
    {
        return $this->score;
    }

    public function maxScore(): int
    {
        return $this->maxScore;
    }

    public function passed(): ?bool
    {
        return $this->passed;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function submittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function gradedBy(): ?int
    {
        return $this->gradedBy;
    }

    public function gradedAt(): ?\DateTimeImmutable
    {
        return $this->gradedAt;
    }

    /** @return array<string, string> */
    public function answers(): array
    {
        return $this->answers;
    }

    public function answerOf(string $questionId): ?string
    {
        return $this->answers[$questionId] ?? null;
    }

    /** @return array<string, array{score: int, correct: bool}> */
    public function autoGrades(): array
    {
        return $this->autoGrades;
    }

    /** @return array<string, array{score: int, note: string}> */
    public function manualGrades(): array
    {
        return $this->manualGrades;
    }

    public function autoScore(): int
    {
        $sum = 0;
        foreach ($this->autoGrades as $grade) {
            $sum += (int) ($grade['score'] ?? 0);
        }

        return $sum;
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
            'quiz_id' => $this->quizId,
            'student_id' => $this->studentId,
            'answers' => (string) \json_encode([
                'answers' => (object) $this->answers,
                'auto' => (object) $this->autoGrades,
                'grades' => (object) $this->manualGrades,
                'auto_score' => $this->autoScore(),
                'max_score' => $this->maxScore,
            ], JSON_UNESCAPED_UNICODE),
            'score' => $this->score,
            'passed' => $this->passed,
            'status' => $this->status->value,
            'started_at' => self::fmt($this->startedAt),
            'submitted_at' => self::fmt($this->submittedAt),
            'graded_by' => $this->gradedBy,
            'graded_at' => self::fmt($this->gradedAt),
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
