<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * QuizWasPassed — آزمون با موفقیت گذرانده شد (DOC-095 §3.3/§5).
 */

namespace Modules\Lms\Domain\Events;

/**
 * مصرف‌کننده: progress، badge، certificate check.
 *
 * @api 1.0
 */
final readonly class QuizWasPassed
{
    public function __construct(
        public string $attemptId,
        public string $quizId,
        public int $studentId,
        public string $courseId,
        public int $score,
        public int $maxScore,
        public int $passScore,
    ) {
    }

    public function eventName(): string
    {
        return 'lms.quiz.passed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'attempt_id' => $this->attemptId,
            'quiz_id' => $this->quizId,
            'student_id' => $this->studentId,
            'course_id' => $this->courseId,
            'score' => $this->score,
            'max_score' => $this->maxScore,
            'pass_score' => $this->passScore,
        ];
    }
}
