<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * QuizAdminService — مدیریت آزمون/سؤال (DOC-095 §4: instructor).
 */

namespace Modules\Lms\Application\UseCases;

use Modules\Lms\Domain\Entities\Question;
use Modules\Lms\Domain\Entities\Quiz;
use Modules\Lms\Domain\Enums\QuestionType;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * @api 1.0
 * @see modules/lms/README.md
 */
final class QuizAdminService
{
    /**
     * @param array<string, mixed> $config کلید config('lms.quiz')
     */
    public function __construct(
        private readonly QuizRepositoryInterface $quizzes,
        private readonly CourseRepositoryInterface $courses,
        private readonly array $config = [],
    ) {
    }

    // ─── آزمون ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     */
    public function createQuiz(string $courseId, array $input, ?string $lessonId = null): Quiz
    {
        if ($this->courses->findById($courseId) === null) {
            throw new ValidationException(
                errors: ['course' => ['دوره یافت نشد.']],
                messageId: 'lms::messages.course_not_found',
            );
        }

        $validated = Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'time_limit' => ['nullable', 'integer', 'min:1', 'max:600'],
            'pass_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], attributes: ['title' => 'عنوان آزمون', 'pass_score' => 'حد نصاب قبولی']);

        $quiz = Quiz::create(
            courseId: $courseId,
            title: (string) $validated['title'],
            lessonId: $lessonId,
            timeLimit: ($validated['time_limit'] ?? null) === null ? $this->defaultTimeLimit() : (int) $validated['time_limit'],
            passScore: ($validated['pass_score'] ?? null) === null ? (int) ($this->config['default_pass_score'] ?? 50) : (int) $validated['pass_score'],
            maxAttempts: ($validated['max_attempts'] ?? null) === null ? (int) ($this->config['default_max_attempts'] ?? 1) : (int) $validated['max_attempts'],
        );

        $this->quizzes->saveQuiz($quiz);

        return $quiz;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateQuiz(Quiz $quiz, array $input): Quiz
    {
        $validated = Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'time_limit' => ['nullable', 'integer', 'min:1', 'max:600'],
            'pass_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], attributes: ['title' => 'عنوان آزمون']);

        $quiz->revise(
            title: (string) $validated['title'],
            timeLimit: ($validated['time_limit'] ?? null) === null ? null : (int) $validated['time_limit'],
            passScore: ($validated['pass_score'] ?? null) === null ? null : (int) $validated['pass_score'],
            maxAttempts: ($validated['max_attempts'] ?? null) === null ? null : (int) $validated['max_attempts'],
            clearTimeLimit: \array_key_exists('time_limit', $input) && ($input['time_limit'] === null || $input['time_limit'] === ''),
        );

        $this->quizzes->saveQuiz($quiz);

        return $quiz;
    }

    public function deleteQuiz(Quiz $quiz): void
    {
        $this->quizzes->deleteQuiz($quiz->id());
    }

    // ─── سؤال ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     */
    public function addQuestion(Quiz $quiz, array $input): Question
    {
        $validated = $this->validateQuestion($input);

        $existing = $this->quizzes->questionsOf($quiz->id());

        $question = Question::create(
            quizId: $quiz->id(),
            type: QuestionType::tryFromString((string) ($validated['type'] ?? 'multiple_choice')),
            body: (string) $validated['body'],
            options: $this->parseOptions($validated['options'] ?? null),
            correctAnswer: ($validated['correct_answer'] ?? null) === null || $validated['correct_answer'] === '' ? null : (string) $validated['correct_answer'],
            score: (int) ($validated['score'] ?? 1),
            position: ($validated['position'] ?? null) === null ? \count($existing) : (int) $validated['position'],
        );

        $this->quizzes->saveQuestion($question);

        return $question;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateQuestion(Question $question, array $input): Question
    {
        $validated = $this->validateQuestion($input);

        $question->revise(
            type: QuestionType::tryFromString((string) ($validated['type'] ?? 'multiple_choice')),
            body: (string) $validated['body'],
            options: $this->parseOptions($validated['options'] ?? null),
            correctAnswer: ($validated['correct_answer'] ?? null) === null || $validated['correct_answer'] === '' ? null : (string) $validated['correct_answer'],
            score: (int) ($validated['score'] ?? 1),
            position: ($validated['position'] ?? null) === null ? null : (int) $validated['position'],
        );

        $this->quizzes->saveQuestion($question);

        return $question;
    }

    public function deleteQuestion(Question $question): void
    {
        $this->quizzes->deleteQuestion($question->id());
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validateQuestion(array $input): array
    {
        return Validator::validate($input, [
            'type' => ['required', 'in:multiple_choice,true_false,short_answer,descriptive'],
            'body' => ['required', 'string', 'max:5000'],
            'options' => ['nullable'],
            'correct_answer' => ['nullable', 'string', 'max:2000'],
            'score' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'position' => ['nullable', 'integer', 'min:0'],
        ], attributes: [
            'body' => 'متن سؤال',
            'type' => 'نوع سؤال',
            'correct_answer' => 'پاسخ صحیح',
        ]);
    }

    /**
     * گزینه‌ها: آرایهٔ [{key,text}] | JSON | خطوط «key=text».
     *
     * @return list<array{key: string, text: string}>
     */
    private function parseOptions(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (\is_string($raw)) {
            $decoded = \json_decode($raw, true);
            if (\is_array($decoded)) {
                $raw = $decoded;
            } else {
                $options = [];
                foreach (\preg_split('/\r\n|\n|\r/', $raw) ?: [] as $line) {
                    $line = \trim($line);
                    if ($line === '') {
                        continue;
                    }
                    [$key, $text] = \array_pad(\explode('=', $line, 2), 2, '');
                    $options[] = ['key' => \trim($key), 'text' => \trim((string) $text)];
                }

                return $options;
            }
        }

        if (!\is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $item) {
            if (\is_array($item) && isset($item['key'], $item['text'])) {
                $options[] = ['key' => (string) $item['key'], 'text' => (string) $item['text']];
            }
        }

        return $options;
    }

    private function defaultTimeLimit(): ?int
    {
        $limit = $this->config['default_time_limit'] ?? null;

        return $limit === null ? null : \max(1, (int) $limit);
    }
}
