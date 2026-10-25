<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * AttemptWasGraded — نمره‌دهی دستی تلاش کامل شد (DOC-095 §3.3).
 */

namespace Modules\Lms\Domain\Events;

/**
 * مصرف‌کننده: notification (نتیجهٔ آزمون به دانشجو).
 *
 * @api 1.0
 */
final readonly class AttemptWasGraded
{
    public function __construct(
        public string $attemptId,
        public string $quizId,
        public int $studentId,
        public string $courseId,
        public int $score,
        public int $maxScore,
        public bool $passed,
        public int $gradedBy,
    ) {
    }

    public function eventName(): string
    {
        return 'lms.attempt.graded';
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
            'passed' => $this->passed,
            'graded_by' => $this->gradedBy,
        ];
    }
}
