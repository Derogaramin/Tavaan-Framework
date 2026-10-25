<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * QuizRepositoryInterface — پورت مخزن آزمون/سؤال/تلاش.
 */

namespace Modules\Lms\Domain\Repositories;

use Modules\Lms\Domain\Entities\Question;
use Modules\Lms\Domain\Entities\Quiz;
use Modules\Lms\Domain\Entities\QuizAttempt;

/**
 * @api 1.0
 */
interface QuizRepositoryInterface
{
    public function saveQuiz(Quiz $quiz): void;

    public function findQuizById(string $id): ?Quiz;

    public function deleteQuiz(string $id): void;

    /** آزمون‌های یک دوره. @return list<Quiz> */
    public function quizzesOfCourse(string $courseId): array;

    public function saveQuestion(Question $question): void;

    public function findQuestionById(string $id): ?Question;

    public function deleteQuestion(string $id): void;

    /** سؤال‌های آزمون (مرتب بر position). @return list<Question> */
    public function questionsOf(string $quizId): array;

    // ── تلاش‌ها ───────────────────────────────────────────────────────────

    public function saveAttempt(QuizAttempt $attempt): void;

    public function findAttemptById(string $id): ?QuizAttempt;

    /** تلاش‌های یک دانشجو برای یک آزمون (صعودی). @return list<QuizAttempt> */
    public function attemptsOf(int $studentId, string $quizId): array;

    /** آخرین تلاش (برای ادامه/نمایش). */
    public function latestAttempt(int $studentId, string $quizId): ?QuizAttempt;

    /** آیا تلاش قبول‌شده‌ای برای این (دانشجو، آزمون) هست؟ */
    public function hasPassedAttempt(int $studentId, string $quizId): bool;

    /**
     * صف نمره‌دهی دستی (status=submitted) — پنل مدرس/assistant.
     *
     * @return array{rows: list<QuizAttempt>, total: int}
     */
    public function paginateAwaitingGrading(int $page = 1, int $perPage = 20, ?string $courseId = null): array;

    /** همهٔ آزمون‌های یک دوره که دانشجو «پذیرفته» — برای بررسی صدور گواهی.
     *
     * @return array{total: int, passed: int}
     */
    public function passStatusForStudentInCourse(int $studentId, string $courseId): array;
}
