<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * QuizService — آزمون و نمره‌دهی (DOC-095 §3.3).
 */

namespace Modules\Lms\Application\UseCases;

use Modules\Lms\Domain\Entities\Enrollment;
use Modules\Lms\Domain\Entities\Question;
use Modules\Lms\Domain\Entities\Quiz;
use Modules\Lms\Domain\Entities\QuizAttempt;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Modules\Lms\Domain\Services\QuizGrader;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;

/**
 * QuizService — جریان §3.3:
 *
 * ```
 * start attempt (max_attempts/time_limit)
 *   → submit answers → نمره‌دهی خودکار (mc/true_false/short*)
 *   → دستی (descriptive) توسط مدرس → finalize
 *   → pass → event QuizWasPassed → تکمیل درسِ آزمون → certificate check
 * ```
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class QuizService
{
    /**
     * @param array<string, mixed> $config کلید config('lms')
     */
    public function __construct(
        private readonly QuizRepositoryInterface $quizzes,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly QuizGrader $grader,
        private readonly ProgressService $progress,
        private readonly array $config = [],
    ) {
    }

    /** شروع/ادامهٔ تلاش (DOC-095 §3.3: POST /lms/quizzes/{id}/attempt). */
    public function startAttempt(Enrollment $enrollment, Quiz $quiz): QuizAttempt
    {
        if (!$enrollment->status()->grantsAccess()) {
            throw new ValidationException(
                errors: ['enrollment' => ['برای شرکت در آزمون باید ثبت‌نام فعال داشته باشید.']],
                messageId: 'lms::messages.not_enrolled',
            );
        }

        if ($this->quizzes->hasPassedAttempt($enrollment->studentId(), $quiz->id())) {
            throw new ValidationException(
                errors: ['quiz' => ['این آزمون را پیش‌تر با موفقیت گذرانده‌اید.']],
                messageId: 'lms::messages.already_passed',
            );
        }

        // تلاش در جریان (و در پنجرهٔ زمانی) → ادامهٔ همان
        $latest = $this->quizzes->latestAttempt($enrollment->studentId(), $quiz->id());
        if ($latest !== null && !$latest->isGraded() && $latest->status() === \Modules\Lms\Domain\Enums\AttemptStatus::InProgress
            && $quiz->isWithinTimeLimit($latest->startedAt())) {
            return $latest;
        }

        $attempts = $this->quizzes->attemptsOf($enrollment->studentId(), $quiz->id());
        if (\count($attempts) >= $quiz->maxAttempts()) {
            throw new ValidationException(
                errors: ['quiz' => ['به سقف تعداد تلاش‌های این آزمون رسیده‌اید.']],
                messageId: 'lms::messages.max_attempts_reached',
            );
        }

        $attempt = QuizAttempt::start($quiz->id(), $enrollment->studentId());
        $this->quizzes->saveAttempt($attempt);

        return $attempt;
    }

    /**
     * ثبت نهایی پاسخ‌ها + نمره‌دهی خودکار.
     *
     * @param array<string, string> $answers questionId → پاسخ
     *
     * @return array{attempt: QuizAttempt, awaitingManual: bool, passed: ?bool, score: int, maxScore: int}
     */
    public function submitAttempt(QuizAttempt $attempt, Quiz $quiz, array $answers): array
    {
        if ($attempt->status() !== \Modules\Lms\Domain\Enums\AttemptStatus::InProgress) {
            throw new ValidationException(
                errors: ['attempt' => ['این تلاش پیش‌تر ثبت نهایی شده است.']],
                messageId: 'lms::messages.attempt_already_submitted',
            );
        }

        // 🔒 time_limit — ارسال پس از انقضا رد می‌شود (timer انسانی DOC-095 §6)
        if (!$quiz->isWithinTimeLimit($attempt->startedAt())) {
            throw new ValidationException(
                errors: ['attempt' => ['زمان آزمون به پایان رسیده است.']],
                messageId: 'lms::messages.quiz_time_expired',
            );
        }

        $questions = $this->quizzes->questionsOf($quiz->id());
        if ($questions === []) {
            throw new ValidationException(
                errors: ['quiz' => ['این آزمون هنوز سؤالی ندارد.']],
                messageId: 'lms::messages.quiz_has_no_questions',
            );
        }

        // 🔒 فقط پاسخ سؤالِ متعلق به همین آزمون پذیرفته می‌شود
        $allowed = [];
        foreach ($questions as $question) {
            $qid = $question->id();
            $allowed[$qid] = (string) ($answers[$qid] ?? '');
        }

        $attempt->fillAnswers($allowed);

        $result = $this->grader->grade($questions, $allowed, $quiz->passScore());

        $attempt->submit(
            autoGrades: $result['auto'],
            autoScore: $result['auto_score'],
            maxScore: $result['max_score'],
            hasManual: $result['has_manual'],
            passedIfNoManual: $result['passed'],
            passScorePercent: $quiz->passScore(),
            courseId: $quiz->courseId(),
        );
        $this->quizzes->saveAttempt($attempt);

        $this->flushEvents($attempt);

        if ($attempt->passed() === true) {
            $this->onQuizPassed($attempt, $quiz);
        }

        return [
            'attempt' => $attempt,
            'awaitingManual' => $attempt->isAwaitingManualGrading(),
            'passed' => $attempt->passed(),
            'score' => $attempt->score(),
            'maxScore' => $attempt->maxScore(),
        ];
    }

    /**
     * نمره‌دهی دستی (مدرس/assistant — DOC-095 §3.3).
     *
     * @param array<string, array{score: int, note?: string}> $grades questionId → نمره
     */
    public function gradeAttempt(QuizAttempt $attempt, Quiz $quiz, array $grades, int $graderId): QuizAttempt
    {
        if (!$attempt->isAwaitingManualGrading()) {
            throw new ValidationException(
                errors: ['attempt' => ['این تلاش در وضعیت نمره‌دهی دستی نیست.']],
                messageId: 'lms::messages.attempt_not_gradeable',
            );
        }

        $questions = $this->quizzes->questionsOf($quiz->id());
        $manualIds = [];
        $maxScore = 0;
        foreach ($questions as $question) {
            $maxScore += $question->score();
            if ($question->needsManualGrading($this->autoGradeShortAnswer())) {
                $manualIds[] = $question->id();
            }
        }

        foreach ($manualIds as $qid) {
            $question = $this->questionById($questions, $qid);
            if ($question === null) {
                continue;
            }
            $score = (int) ($grades[$qid]['score'] ?? 0);
            $note = (string) ($grades[$qid]['note'] ?? '');
            $attempt->gradeQuestion($qid, $score, $question->score(), $note);
        }

        $finalScore = $attempt->autoScore();
        foreach ($attempt->manualGrades() as $g) {
            $finalScore += (int) ($g['score'] ?? 0);
        }
        $passed = QuizGrader::meetsPassThreshold($finalScore, $maxScore, $quiz->passScore());

        $attempt->finalizeGrading($manualIds, $graderId, $passed, $quiz->courseId(), $quiz->passScore());
        $this->quizzes->saveAttempt($attempt);

        $this->flushEvents($attempt);

        if ($passed) {
            $this->onQuizPassed($attempt, $quiz);
        }

        return $attempt;
    }

    /** نتیجهٔ تفکیکی تلاش برای نمایش (خودکار/دستی/بدون پاسخ صحیح). */
    public function attemptBreakdown(QuizAttempt $attempt, Quiz $quiz): array
    {
        $questions = $this->quizzes->questionsOf($quiz->id());
        $rows = [];
        foreach ($questions as $question) {
            $qid = $question->id();
            $rows[] = [
                'question' => $question,
                'answer' => $attempt->answerOf($qid),
                'auto' => $attempt->autoGrades()[$qid] ?? null,
                'manual' => $attempt->manualGrades()[$qid] ?? null,
                'is_manual' => $question->needsManualGrading($this->autoGradeShortAnswer()),
            ];
        }

        return $rows;
    }

    /** @return list<Question> */
    public function questionsOf(Quiz $quiz): array
    {
        return $this->quizzes->questionsOf($quiz->id());
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function onQuizPassed(QuizAttempt $attempt, Quiz $quiz): void
    {
        // ۱) درسِ نوع quiz تکمیل می‌شود (config) → recalc پیشرفت
        $result = $this->progress->completeQuizLesson($attempt->studentId(), $quiz->courseId(), $quiz->id());

        // ۲) دوره پیش‌تر کامل شده و این آخرین آزمونِ قبولی است → گواهی
        if ($result === null) {
            $enrollment = $this->enrollments->find($attempt->studentId(), $quiz->courseId());
            if ($enrollment !== null && $enrollment->isCompleted()) {
                $this->progress->maybeIssueCertificate($enrollment);
            }
        }
    }

    /** @param list<Question> $questions */
    private function questionById(array $questions, string $id): ?Question
    {
        foreach ($questions as $question) {
            if ($question->id() === $id) {
                return $question;
            }
        }

        return null;
    }

    private function autoGradeShortAnswer(): bool
    {
        return (bool) ($this->config['quiz']['auto_grade_short_answer'] ?? true);
    }

    private function flushEvents(QuizAttempt $attempt): void
    {
        foreach ($attempt->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
