<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * QuizGrader — نمره‌دهی خودکار آزمون (DOC-095 §3.3).
 */

namespace Modules\Lms\Domain\Services;

use Modules\Lms\Domain\Entities\Question;
use Modules\Lms\Domain\Enums\QuestionType;

/**
 * QuizGrader — تصمیم خالص (بدون I/O):
 *
 * - multiple_choice: کلید گزینه == correct_answer
 * - true_false: نگاشت رایج فارسی/لاتین == correct_answer
 * - short_answer: متن نرمال‌شده == پاسخ مرجع (config: auto_grade_short_answer)
 * - descriptive: همیشه دستی
 *
 * خروجی: auto grades، auto_score، max_score، hasManual و passed
 * (pass_score درصدی از max_score — گرد به بالا به نفع دانشجو در آستانه).
 *
 * @api 1.0
 */
final class QuizGrader
{
    public function __construct(
        private readonly AnswerNormalizer $normalizer = new AnswerNormalizer(),
        private readonly bool $autoGradeShortAnswer = true,
    ) {
    }

    /**
     * نمره‌دهی خودکار.
     *
     * @param list<Question> $questions
     * @param array<string, string> $answers questionId → پاسخ
     *
     * @return array{
     *     auto: array<string, array{score: int, correct: bool}>,
     *     auto_score: int,
     *     max_score: int,
     *     has_manual: bool,
     *     manual_question_ids: list<string>,
     *     passed: bool
     * }
     */
    public function grade(array $questions, array $answers, int $passScorePercent): array
    {
        $auto = [];
        $autoScore = 0;
        $maxScore = 0;
        $manualIds = [];

        foreach ($questions as $question) {
            $maxScore += $question->score();
            $given = (string) ($answers[$question->id()] ?? '');

            if (!$question->isAutoGraded($this->autoGradeShortAnswer)) {
                $manualIds[] = $question->id();

                continue;
            }

            $correct = $this->isCorrect($question, $given);
            $earned = $correct ? $question->score() : 0;

            $auto[$question->id()] = ['score' => $earned, 'correct' => $correct];
            $autoScore += $earned;
        }

        return [
            'auto' => $auto,
            'auto_score' => $autoScore,
            'max_score' => $maxScore,
            'has_manual' => $manualIds !== [],
            'manual_question_ids' => $manualIds,
            'passed' => $manualIds === [] && $maxScore > 0 && self::meetsPassThreshold($autoScore, $maxScore, $passScorePercent),
        ];
    }

    /** آیا نمرهٔ نهایی به آستانهٔ قبولی رسیده؟ (درصدی از max) */
    public static function meetsPassThreshold(int $score, int $maxScore, int $passScorePercent): bool
    {
        if ($maxScore <= 0) {
            return false;
        }

        // score*100 >= pass*max — مقایسهٔ int (بدون float)
        return $score * 100 >= $passScorePercent * $maxScore;
    }

    private function isCorrect(Question $question, string $given): bool
    {
        $expected = (string) ($question->correctAnswer() ?? '');
        if ($expected === '') {
            return false;
        }

        return match ($question->type()) {
            QuestionType::MultipleChoice => $this->normalizer->normalize($given) === $this->normalizer->normalize($expected),
            QuestionType::TrueFalse => $this->normalizer->booleanEquals($given, $expected),
            QuestionType::ShortAnswer => $this->normalizer->textEquals($given, $expected),
            QuestionType::Descriptive => false,
        };
    }
}
