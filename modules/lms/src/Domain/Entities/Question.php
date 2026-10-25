<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * Question — سؤال آزمون (DOC-095 §2: type/options/correct_answer/score).
 */

namespace Modules\Lms\Domain\Entities;

use Modules\Lms\Domain\Enums\QuestionType;

/**
 * Question — options برای multiple_choice: [{key,text},…]؛
 * correct_answer: کلید گزینه | 'true'/'false' | متن مرجع (short_answer).
 *
 * @api 1.0
 */
final class Question
{
    private function __construct(
        private readonly string $id,
        private readonly string $quizId,
        private QuestionType $type,
        private string $body,
        /** @var list<array{key: string, text: string}> */
        private array $options,
        private ?string $correctAnswer,
        private int $score,
        private int $position,
    ) {
    }

    /**
     * @param list<array{key: string, text: string}> $options
     */
    public static function create(
        string $quizId,
        QuestionType $type,
        string $body,
        array $options = [],
        ?string $correctAnswer = null,
        int $score = 1,
        int $position = 0,
        ?\DateTimeImmutable $at = null,
    ): self {
        $question = new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $quizId,
            $type,
            $body,
            $options,
            $correctAnswer,
            $score,
            $position,
        );
        $question->assertValid();

        return $question;
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $options = [];
        $raw = $row['options'] ?? null;
        if (\is_string($raw) && $raw !== '') {
            $decoded = \json_decode($raw, true);
            $options = \is_array($decoded) ? $decoded : [];
        } elseif (\is_array($raw)) {
            $options = $raw;
        }

        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['quiz_id'] ?? ''),
            QuestionType::tryFromString((string) ($row['type'] ?? 'multiple_choice')),
            (string) ($row['body'] ?? ''),
            $options,
            isset($row['correct_answer']) && $row['correct_answer'] !== null && $row['correct_answer'] !== '' ? (string) $row['correct_answer'] : null,
            (int) ($row['score'] ?? 1),
            (int) ($row['position'] ?? 0),
        );
    }

    /**
     * @param list<array{key: string, text: string}> $options
     */
    public function revise(QuestionType $type, string $body, array $options = [], ?string $correctAnswer = null, ?int $score = null, ?int $position = null): void
    {
        $this->type = $type;
        $this->body = $body;
        $this->options = $options;
        $this->correctAnswer = $correctAnswer;
        $this->score = $score === null ? $this->score : \max(1, $score);
        $this->position = $position ?? $this->position;
        $this->assertValid();
    }

    /** آیا نمرهٔ این سؤال خودکار محاسبه می‌شود؟ (DOC-095 §3.3) */
    public function isAutoGraded(bool $autoGradeShortAnswer = true): bool
    {
        if (!$this->type->isAutoGradeable()) {
            return false;
        }
        if ($this->correctAnswer === null || $this->correctAnswer === '') {
            return false;   // پاسخ مرجع ندارد → دستی
        }
        if ($this->type === QuestionType::ShortAnswer && !$autoGradeShortAnswer) {
            return false;
        }

        return true;
    }

    /** آیا منتظر نمره‌دهی دستی است؟ */
    public function needsManualGrading(bool $autoGradeShortAnswer = true): bool
    {
        return !$this->isAutoGraded($autoGradeShortAnswer);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function quizId(): string
    {
        return $this->quizId;
    }

    public function type(): QuestionType
    {
        return $this->type;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return list<array{key: string, text: string}> */
    public function options(): array
    {
        return $this->options;
    }

    public function correctAnswer(): ?string
    {
        return $this->correctAnswer;
    }

    public function score(): int
    {
        return $this->score;
    }

    public function position(): int
    {
        return $this->position;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quizId,
            'type' => $this->type->value,
            'body' => $this->body,
            'options' => (string) \json_encode($this->options, JSON_UNESCAPED_UNICODE),
            'correct_answer' => $this->correctAnswer,
            'score' => $this->score,
            'position' => $this->position,
        ];
    }

    private function assertValid(): void
    {
        if (\trim($this->body) === '') {
            throw new \DomainException('متن سؤال نمی‌تواند خالی باشد.');
        }
        if ($this->score < 1) {
            throw new \DomainException('نمرهٔ سؤال باید ≥ ۱ باشد.');
        }
        if ($this->type === QuestionType::MultipleChoice && \count($this->options) < 2) {
            throw new \DomainException('سؤال چهارگزینه‌ای حداقل دو گزینه می‌خواهد.');
        }
        if ($this->type === QuestionType::MultipleChoice
            && $this->correctAnswer !== null && $this->correctAnswer !== '') {
            $keys = \array_column($this->options, 'key');
            if (!\in_array($this->correctAnswer, $keys, true)) {
                throw new \DomainException('پاسخ صحیح باید یکی از کلیدهای گزینه‌ها باشد.');
            }
        }
        if ($this->type === QuestionType::TrueFalse
            && $this->correctAnswer !== null && $this->correctAnswer !== ''
            && !\in_array(\strtolower($this->correctAnswer), ['true', 'false'], true)) {
            throw new \DomainException('پاسخ صحیح سؤال صحیح/غلط باید true یا false باشد.');
        }
    }
}
