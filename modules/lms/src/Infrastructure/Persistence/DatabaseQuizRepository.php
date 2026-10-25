<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * DatabaseQuizRepository — آداپتر آزمون/سؤال/تلاش.
 */

namespace Modules\Lms\Infrastructure\Persistence;

use Modules\Lms\Domain\Entities\Question;
use Modules\Lms\Domain\Entities\Quiz;
use Modules\Lms\Domain\Entities\QuizAttempt;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/lms/README.md
 */
final class DatabaseQuizRepository implements QuizRepositoryInterface
{
    public function saveQuiz(Quiz $quiz): void
    {
        $row = $quiz->toRow();
        $exists = DB::table('lms_quizzes')->where('id', $quiz->id())->exists();

        if ($exists) {
            unset($row['id']);
            DB::table('lms_quizzes')->where('id', $quiz->id())->update($row);
        } else {
            DB::table('lms_quizzes')->insert($row);
        }
    }

    public function findQuizById(string $id): ?Quiz
    {
        $row = DB::table('lms_quizzes')->where('id', $id)->first();

        return $row === null ? null : Quiz::fromRow($row);
    }

    public function deleteQuiz(string $id): void
    {
        DB::table('lms_questions')->where('quiz_id', $id)->delete();
        DB::table('lms_quiz_attempts')->where('quiz_id', $id)->delete();
        DB::table('lms_lessons')->where('quiz_id', $id)->update(['quiz_id' => null, 'type' => 'text']);
        DB::table('lms_quizzes')->where('id', $id)->delete();
    }

    public function quizzesOfCourse(string $courseId): array
    {
        $rows = DB::table('lms_quizzes')->where('course_id', $courseId)->orderBy('title')->get();

        return \array_map(static fn (array $row): Quiz => Quiz::fromRow($row), $rows);
    }

    public function saveQuestion(Question $question): void
    {
        $row = $question->toRow();
        $exists = DB::table('lms_questions')->where('id', $question->id())->exists();

        if ($exists) {
            unset($row['id']);
            DB::table('lms_questions')->where('id', $question->id())->update($row);
        } else {
            DB::table('lms_questions')->insert($row);
        }
    }

    public function findQuestionById(string $id): ?Question
    {
        $row = DB::table('lms_questions')->where('id', $id)->first();

        return $row === null ? null : Question::fromRow($row);
    }

    public function deleteQuestion(string $id): void
    {
        DB::table('lms_questions')->where('id', $id)->delete();
    }

    public function questionsOf(string $quizId): array
    {
        $rows = DB::table('lms_questions')->where('quiz_id', $quizId)->orderBy('position')->orderBy('id')->get();

        return \array_map(static fn (array $row): Question => Question::fromRow($row), $rows);
    }

    // ── تلاش‌ها ───────────────────────────────────────────────────────────

    public function saveAttempt(QuizAttempt $attempt): void
    {
        $row = $attempt->toRow();
        $exists = DB::table('lms_quiz_attempts')->where('id', $attempt->id())->exists();

        if ($exists) {
            unset($row['id'], $row['started_at']);
            DB::table('lms_quiz_attempts')->where('id', $attempt->id())->update($row);
        } else {
            DB::table('lms_quiz_attempts')->insert($row);
        }
    }

    public function findAttemptById(string $id): ?QuizAttempt
    {
        $row = DB::table('lms_quiz_attempts')->where('id', $id)->first();

        return $row === null ? null : QuizAttempt::fromRow($row);
    }

    public function attemptsOf(int $studentId, string $quizId): array
    {
        $rows = DB::table('lms_quiz_attempts')
            ->where('student_id', $studentId)
            ->where('quiz_id', $quizId)
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        return \array_map(static fn (array $row): QuizAttempt => QuizAttempt::fromRow($row), $rows);
    }

    public function latestAttempt(int $studentId, string $quizId): ?QuizAttempt
    {
        $row = DB::table('lms_quiz_attempts')
            ->where('student_id', $studentId)
            ->where('quiz_id', $quizId)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : QuizAttempt::fromRow($row);
    }

    public function hasPassedAttempt(int $studentId, string $quizId): bool
    {
        return DB::table('lms_quiz_attempts')
            ->where('student_id', $studentId)
            ->where('quiz_id', $quizId)
            ->where('passed', true)
            ->exists();
    }

    public function paginateAwaitingGrading(int $page = 1, int $perPage = 20, ?string $courseId = null): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        // صف نمره‌دهی روی تلاش‌های submitted؛ فیلتر دوره با whereIn روی
        // شناسهٔ آزمون‌های همان دوره (جدول attempts ستون course_id ندارد)
        $quizIds = null;
        if ($courseId !== null && $courseId !== '') {
            $quizRows = DB::table('lms_quizzes')->where('course_id', $courseId)->get();
            $quizIds = \array_map(static fn (array $row): string => (string) $row['id'], $quizRows);
            if ($quizIds === []) {
                return ['rows' => [], 'total' => 0];
            }
        }

        $count = DB::table('lms_quiz_attempts')->where('status', 'submitted');
        $rows = DB::table('lms_quiz_attempts')->where('status', 'submitted');

        if ($quizIds !== null) {
            $count->whereIn('quiz_id', $quizIds);
            $rows->whereIn('quiz_id', $quizIds);
        }

        $total = $count->count();
        $data = $rows->orderBy('submitted_at')->orderBy('id')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): QuizAttempt => QuizAttempt::fromRow($row), $data),
            'total' => $total,
        ];
    }

    public function passStatusForStudentInCourse(int $studentId, string $courseId): array
    {
        $quizzes = DB::table('lms_quizzes')->where('course_id', $courseId)->get();
        $total = \count($quizzes);
        if ($total === 0) {
            return ['total' => 0, 'passed' => 0];
        }

        $quizIds = \array_map(static fn (array $row): string => (string) $row['id'], $quizzes);

        $passedRows = DB::table('lms_quiz_attempts')
            ->where('student_id', $studentId)
            ->whereIn('quiz_id', $quizIds)
            ->where('passed', true)
            ->get();

        $passedIds = [];
        foreach ($passedRows as $row) {
            $passedIds[(string) $row['quiz_id']] = true;
        }

        return ['total' => $total, 'passed' => \count($passedIds)];
    }
}
