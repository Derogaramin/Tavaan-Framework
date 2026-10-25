<?php

declare(strict_types=1);

namespace Modules\Lms\Tests\Unit;

use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Entities\Enrollment;
use Modules\Lms\Domain\Entities\Lesson;
use Modules\Lms\Domain\Entities\Question;
use Modules\Lms\Domain\Entities\Quiz;
use Modules\Lms\Domain\Entities\QuizAttempt;
use Modules\Lms\Domain\Enums\AttemptStatus;
use Modules\Lms\Domain\Enums\CourseStatus;
use Modules\Lms\Domain\Enums\EnrollmentStatus;
use Modules\Lms\Domain\Enums\LessonType;
use Modules\Lms\Domain\Enums\QuestionType;
use Modules\Lms\Domain\Events\AttemptWasGraded;
use Modules\Lms\Domain\Events\CourseWasCompleted;
use Modules\Lms\Domain\Events\CourseWasPublished;
use Modules\Lms\Domain\Events\QuizWasPassed;
use Modules\Lms\Domain\Events\StudentWasEnrolled;
use Modules\Lms\Domain\Services\AnswerNormalizer;
use Modules\Lms\Domain\Services\CertificateSerialGenerator;
use Modules\Lms\Domain\Services\PrerequisiteChecker;
use Modules\Lms\Domain\Services\ProgressCalculator;
use Modules\Lms\Domain\Services\QuizGrader;
use Modules\Lms\Domain\ValueObjects\Money;
use Tavaan\Testing\TestCase;

/**
 * تست واحد دامنهٔ LMS — موجودیت‌ها/VOها/سرویس‌های خالص (DOC-095).
 */
final class LmsDomainUnitTest extends TestCase
{
    // ─── Course ────────────────────────────────────────────────────────────

    public function testCourseLifecycleAndEvents(): void
    {
        $course = Course::draft(slug: 'php-101', title: 'پی‌اچ‌پی ۱۰۱', instructorId: 7, price: Money::of(1_000_000));

        self::assertSame(CourseStatus::Draft, $course->status());
        self::assertFalse($course->isFree());
        self::assertTrue($course->isPaid());
        self::assertSame(26, \strlen($course->id()));

        // draft → archived نامجاز
        try {
            $course->archive();
            self::fail('انتقال draft→archived باید رد شود');
        } catch (\DomainException) {
            self::assertSame(CourseStatus::Draft, $course->status());
        }

        $course->publish();
        $events = $course->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CourseWasPublished::class, $events[0]);
        self::assertSame('lms.course.published', $events[0]->eventName());
        self::assertSame(1_000_000, $events[0]->toPayload()['price_minor']);
        self::assertNotNull($course->publishedAt());

        // pullEvents پاک می‌کند
        self::assertSame([], $course->pullEvents());

        $course->archive();
        self::assertSame(CourseStatus::Archived, $course->status());

        // archived → published مجاز
        $course->publish();
        self::assertTrue($course->isPublished());
    }

    public function testCourseFreePriceSync(): void
    {
        $course = Course::draft(slug: 'free-1', title: 'دورهٔ رایگان', instructorId: 1, price: Money::zero());
        self::assertTrue($course->isFree());

        $course->revise(title: 'دورهٔ رایگان', price: Money::of(500_000));
        self::assertFalse($course->isFree());

        $course->revise(title: 'دورهٔ رایگان', price: Money::zero());
        self::assertTrue($course->isFree());
    }

    public function testCourseReviseRejectsSelfPrerequisite(): void
    {
        $course = Course::draft(slug: 'c1', title: 'دوره', instructorId: 1);
        $course->revise(title: 'دوره', prerequisiteIds: [$course->id(), 'other-id', 'other-id']);

        self::assertSame(['other-id'], $course->prerequisiteIds());
    }

    // ─── Enrollment ────────────────────────────────────────────────────────

    public function testEnrollmentStateMachine(): void
    {
        $enrollment = Enrollment::pendingPayment(42, 'course-ulid');
        self::assertTrue($enrollment->isPendingPayment());
        self::assertNull($enrollment->enrolledAt());

        // pending → completed نامجاز (باید اول پرداخت شود)
        try {
            $enrollment->complete('دوره');
            self::fail('pending→completed باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        $enrollment->activateAfterPayment('دورهٔ پولی', 2_000_000, 'IRR');
        self::assertSame(EnrollmentStatus::Active, $enrollment->status());
        self::assertNotNull($enrollment->enrolledAt());

        $events = $enrollment->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StudentWasEnrolled::class, $events[0]);
        self::assertTrue($events[0]->toPayload()['paid']);
        self::assertSame('lms.student.enrolled', $events[0]->eventName());

        $enrollment->updateProgress(55.555);
        self::assertSame(55.56, $enrollment->progressPercent());   // round ۲ رقم

        $enrollment->complete('دورهٔ پولی');
        self::assertSame(EnrollmentStatus::Completed, $enrollment->status());
        self::assertSame(100.0, $enrollment->progressPercent());

        $events = $enrollment->pullEvents();
        self::assertInstanceOf(CourseWasCompleted::class, $events[0]);

        // completed پایانی است
        try {
            $enrollment->drop();
            self::fail('drop از completed باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }
    }

    public function testFreeEnrollmentIsImmediatelyActive(): void
    {
        $enrollment = Enrollment::free(1, 'c');
        self::assertSame(EnrollmentStatus::Active, $enrollment->status());

        $enrollment->recordFreeEnrollment('دورهٔ رایگان', 'IRR');
        $events = $enrollment->pullEvents();
        self::assertFalse($events[0]->toPayload()['paid']);
    }

    public function testDroppedCanReopenForPayment(): void
    {
        $enrollment = Enrollment::pendingPayment(1, 'c');
        $enrollment->drop();
        self::assertSame(EnrollmentStatus::Dropped, $enrollment->status());

        $enrollment->reopenForPayment();
        self::assertTrue($enrollment->isPendingPayment());
        self::assertNull($enrollment->paymentAuthority());
    }

    // ─── QuizGrader + AnswerNormalizer ─────────────────────────────────────

    public function testAnswerNormalizerPersianAware(): void
    {
        $n = new AnswerNormalizer();

        // ارقام فارسی/عربی → لاتین + ی/ک + نیم‌فاصله
        self::assertSame('1405', $n->normalize('۱۴۰۵'));
        self::assertSame('1405', $n->normalize('١٤٠٥'));
        self::assertSame('می شود', $n->normalize('می‌شود'));
        self::assertSame('یکدانه', $n->normalize('يكدانه'));

        // true_false با ورودی فارسی
        self::assertTrue($n->booleanEquals('صحیح', 'true'));
        self::assertTrue($n->booleanEquals('خیر', 'FALSE'));
        self::assertFalse($n->booleanEquals('بله', 'false'));
        self::assertFalse($n->booleanEquals('شاید', 'true'));

        // short_answer با پاسخ‌های چندگانه
        self::assertTrue($n->textEquals('۴۲', '42|چهل و دو'));
        self::assertTrue($n->textEquals('چهل و دو', '42|چهل و دو'));
        self::assertFalse($n->textEquals('۴۳', '42'));
        self::assertFalse($n->textEquals('', '42'));
    }

    public function testQuizGraderAutoAndManual(): void
    {
        $quizId = 'quiz-1';
        $mc = Question::create($quizId, QuestionType::MultipleChoice, 'سؤال ۱', [
            ['key' => 'a', 'text' => 'الف'], ['key' => 'b', 'text' => 'ب'],
        ], 'b', score: 2);
        $tf = Question::create($quizId, QuestionType::TrueFalse, 'سؤال ۲', [], 'true', score: 1);
        $sa = Question::create($quizId, QuestionType::ShortAnswer, 'سؤال ۳', [], '۴۲', score: 1);
        $desc = Question::create($quizId, QuestionType::Descriptive, 'سؤال ۴', [], null, score: 2);

        $grader = new QuizGrader();

        $result = $grader->grade(
            [$mc, $tf, $sa, $desc],
            [$mc->id() => 'b', $tf->id() => 'صحیح', $sa->id() => '42', $desc->id() => 'متن تشریحی'],
            50,
        );

        // خودکارها: mc(2) + tf(1) + sa(1 — پاسخ مرجع «۴۲» با '42' برابر است) = 4
        self::assertSame(4, $result['auto_score']);
        self::assertSame(6, $result['max_score']);
        self::assertTrue($result['has_manual']);
        self::assertSame([$desc->id()], $result['manual_question_ids']);
        self::assertFalse($result['passed']);   // دستی باقی است → هنوز pass قطعی نیست
        self::assertTrue($result['auto'][$mc->id()]['correct']);
        self::assertTrue($result['auto'][$tf->id()]['correct']);
        self::assertTrue($result['auto'][$sa->id()]['correct']);
    }

    public function testQuizGraderWrongAnswersAndThreshold(): void
    {
        $q1 = Question::create('q', QuestionType::MultipleChoice, 'س', [['key' => 'a', 'text' => 'الف'], ['key' => 'b', 'text' => 'ب']], 'a', score: 2);
        $q2 = Question::create('q', QuestionType::TrueFalse, 'س۲', [], 'false', score: 2);

        $grader = new QuizGrader();
        $result = $grader->grade([$q1, $q2], [$q1->id() => 'b', $q2->id() => 'true'], 50);

        self::assertSame(0, $result['auto_score']);
        self::assertFalse($result['has_manual']);
        self::assertFalse($result['passed']);

        // آستانه: ۵۰٪ → 2/4 قبول است (int مقایسه — بدون float)
        self::assertTrue(QuizGrader::meetsPassThreshold(2, 4, 50));
        self::assertFalse(QuizGrader::meetsPassThreshold(1, 4, 50));
        self::assertTrue(QuizGrader::meetsPassThreshold(4, 4, 100));
        self::assertFalse(QuizGrader::meetsPassThreshold(0, 0, 50));
    }

    public function testShortAnswerWithoutReferenceIsManual(): void
    {
        $q = Question::create('q', QuestionType::ShortAnswer, 'پاسخ کوتاه بدون مرجع', [], null, score: 1);
        self::assertTrue($q->needsManualGrading());

        $grader = new QuizGrader();
        $result = $grader->grade([$q], [$q->id() => 'هر چیزی'], 50);
        self::assertTrue($result['has_manual']);
    }

    public function testAutoGradeShortAnswerCanBeDisabled(): void
    {
        $q = Question::create('q', QuestionType::ShortAnswer, 'سؤال', [], '42', score: 1);
        $grader = new QuizGrader(new AnswerNormalizer(), false);

        $result = $grader->grade([$q], [$q->id() => '42'], 50);
        self::assertTrue($result['has_manual']);
        self::assertSame(0, $result['auto_score']);
    }

    // ─── QuizAttempt ───────────────────────────────────────────────────────

    public function testAttemptSubmitAutoOnlyPasses(): void
    {
        $q = Question::create('quiz', QuestionType::TrueFalse, 'س', [], 'true', score: 2);
        $grader = new QuizGrader();

        $attempt = QuizAttempt::start('quiz', 5);
        $answers = [$q->id() => 'true'];
        $attempt->fillAnswers($answers);

        $result = $grader->grade([$q], $answers, 50);
        $attempt->submit($result['auto'], $result['auto_score'], $result['max_score'], $result['has_manual'], $result['passed'], 50, 'course-1');

        self::assertTrue($attempt->isGraded());
        self::assertSame(2, $attempt->score());
        self::assertTrue($attempt->passed());

        $events = $attempt->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(QuizWasPassed::class, $events[0]);
        self::assertSame('lms.quiz.passed', $events[0]->eventName());
        self::assertSame('course-1', $events[0]->toPayload()['course_id']);

        // ثبت مجدد → خطا
        $this->expectException(\DomainException::class);
        $attempt->submit($result['auto'], 2, 2, false, true, 50, 'course-1');
    }

    public function testAttemptManualGradingFlow(): void
    {
        $auto = Question::create('quiz', QuestionType::MultipleChoice, 'س۱', [['key' => 'a', 'text' => 'الف'], ['key' => 'b', 'text' => 'ب']], 'a', score: 2);
        $manual = Question::create('quiz', QuestionType::Descriptive, 'س۲', [], null, score: 3);
        $grader = new QuizGrader();

        $attempt = QuizAttempt::start('quiz', 9);
        $answers = [$auto->id() => 'a', $manual->id() => 'پاسخ تشریحی'];
        $attempt->fillAnswers($answers);

        $result = $grader->grade([$auto, $manual], $answers, 60);
        $attempt->submit($result['auto'], $result['auto_score'], $result['max_score'], true, false, 60, 'course-9');

        self::assertTrue($attempt->isAwaitingManualGrading());
        self::assertNull($attempt->passed());
        self::assertSame(2, $attempt->score());

        // نمرهٔ نامعتبر → خطا
        try {
            $attempt->gradeQuestion($manual->id(), 9, 3);
            self::fail('نمرهٔ بیشتر از سقف سؤال باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        // finalize پیش از نمرهٔ همهٔ دستی‌ها → خطا
        try {
            $attempt->finalizeGrading([$manual->id()], 1, true, 'course-9', 60);
            self::fail('finalize بدون نمرهٔ دستی باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        $attempt->gradeQuestion($manual->id(), 2, 3, 'خوب بود');
        // 2+2=4 از 5 → 80٪ ≥ 60٪ → قبول
        $passed = QuizGrader::meetsPassThreshold(4, 5, 60);
        $attempt->finalizeGrading([$manual->id()], 77, $passed, 'course-9', 60);

        self::assertSame(AttemptStatus::Graded, $attempt->status());
        self::assertSame(4, $attempt->score());
        self::assertTrue($attempt->passed());
        self::assertSame(77, $attempt->gradedBy());

        $events = $attempt->pullEvents();
        self::assertCount(2, $events);   // AttemptWasGraded + QuizWasPassed
        self::assertInstanceOf(AttemptWasGraded::class, $events[0]);
        self::assertInstanceOf(QuizWasPassed::class, $events[1]);

        // answers در JSON round-trip سالم می‌ماند
        $row = $attempt->toRow();
        $revived = QuizAttempt::fromRow($row + ['id' => $attempt->id(), 'quiz_id' => 'quiz', 'student_id' => 9, 'status' => 'graded', 'score' => 4, 'passed' => 1, 'started_at' => $attempt->startedAt()->format('Y-m-d H:i:s.v')]);
        self::assertSame('پاسخ تشریحی', $revived->answerOf($manual->id()));
        self::assertSame(2, $revived->autoScore());
        self::assertSame(2, $revived->manualGrades()[$manual->id()]['score']);
    }

    // ─── Quiz / Question ───────────────────────────────────────────────────

    public function testQuizTimeLimit(): void
    {
        $quiz = Quiz::create('c', 'آزمون', timeLimit: 10);
        $start = new \DateTimeImmutable('2026-10-25 10:00:00', new \DateTimeZone('UTC'));

        self::assertTrue($quiz->isWithinTimeLimit($start, new \DateTimeImmutable('2026-10-25 10:09:59', new \DateTimeZone('UTC'))));
        self::assertFalse($quiz->isWithinTimeLimit($start, new \DateTimeImmutable('2026-10-25 10:10:01', new \DateTimeZone('UTC'))));

        $noLimit = Quiz::create('c', 'بدون زمان');
        self::assertTrue($noLimit->isWithinTimeLimit($start, $start->modify('+10 years')));
        self::assertNull($noLimit->timeLimit());
    }

    public function testQuestionValidation(): void
    {
        // چهارگزینه‌ای با کمتر از ۲ گزینه
        try {
            Question::create('q', QuestionType::MultipleChoice, 'س', [['key' => 'a', 'text' => 'الف']]);
            self::fail('گزینهٔ کم باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        // پاسخ صحیح خارج از کلیدها
        try {
            Question::create('q', QuestionType::MultipleChoice, 'س', [['key' => 'a', 'text' => 'الف'], ['key' => 'b', 'text' => 'ب']], 'z');
            self::fail('کلید نامعتبر باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        // true_false با پاسخ نامعتبر
        try {
            Question::create('q', QuestionType::TrueFalse, 'س', [], 'maybe');
            self::fail('پاسخ غیر true/false باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        // نمرهٔ صفر
        try {
            Question::create('q', QuestionType::Descriptive, 'س', [], null, 0);
            self::fail('نمرهٔ < 1 باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        $ok = Question::create('q', QuestionType::TrueFalse, 'س', [], 'TRUE');
        self::assertSame('TRUE', $ok->correctAnswer());
    }

    // ─── Lesson ────────────────────────────────────────────────────────────

    public function testLessonQuizTypeRequiresQuizId(): void
    {
        try {
            Lesson::create('sec', 'course', 'درس آزمون', 'quiz-lesson', LessonType::Quiz);
            self::fail('درس آزمون بدون quiz_id باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        $lesson = Lesson::create('sec', 'course', 'درس آزمون', 'quiz-lesson', LessonType::Quiz, quizId: 'q-1');
        self::assertSame('lms_lesson', $lesson->commentableType());
        self::assertTrue($lesson->commentsOpen());
    }

    // ─── ProgressCalculator ────────────────────────────────────────────────

    public function testProgressCalculator(): void
    {
        $calc = new ProgressCalculator();

        self::assertSame(0.0, $calc->percent(0, 0));
        self::assertSame(33.33, $calc->percent(1, 3));
        self::assertSame(66.66, $calc->percent(2, 3));   // گرد به پایین — 66.666→66.66
        self::assertSame(100.0, $calc->percent(3, 3));
        self::assertSame(100.0, $calc->percent(9, 3));   // clamp

        self::assertFalse($calc->isCourseComplete(0, 0));
        self::assertFalse($calc->isCourseComplete(2, 3));
        self::assertTrue($calc->isCourseComplete(3, 3));
    }

    // ─── PrerequisiteChecker ───────────────────────────────────────────────

    public function testPrerequisiteChecker(): void
    {
        $checker = new PrerequisiteChecker();

        self::assertSame([], $checker->missing(['a'], ['a', 'b']));
        self::assertSame(['b'], $checker->missing(['a', 'b'], ['a']));
        self::assertTrue($checker->satisfied(['a'], ['a']));
        self::assertFalse($checker->satisfied(['a', 'b'], ['a']));

        // چرخه
        self::assertTrue($checker->hasCycle(['a' => ['b'], 'b' => ['a']]));
        self::assertFalse($checker->hasCycle(['a' => ['b'], 'b' => [], 'c' => ['a', 'b']]));
    }

    // ─── CertificateSerialGenerator ────────────────────────────────────────

    public function testCertificateSerialGenerator(): void
    {
        $gen = new CertificateSerialGenerator('TVN-LMS');

        $seen = [];
        for ($i = 0; $i < 50; $i++) {
            $serial = $gen->generate();
            self::assertTrue($gen->isValidFormat($serial), 'serial معتبر: ' . $serial);
            $seen[$serial] = true;
        }
        self::assertCount(50, $seen);   // یکتایی عملی

        // tamper — رقم کنترل
        $serial = $gen->generate();
        $body = \explode('-', $serial);
        $tamperedChar = $body[1][0] === 'A' ? 'B' : 'A';
        $body[1][0] = $tamperedChar;
        self::assertFalse($gen->isValidFormat(\implode('-', $body)));

        // قالب‌های بی‌ربط
        self::assertFalse($gen->isValidFormat(''));
        self::assertFalse($gen->isValidFormat('TVN-LMS-short'));
        self::assertFalse($gen->isValidFormat('XXX-0123456789-0'));
        self::assertFalse($gen->isValidFormat('TVN-LMS-01234567IL-0'));   // I/L مجاز نیست
    }

    // ─── Money ─────────────────────────────────────────────────────────────

    public function testMoneyValueObject(): void
    {
        $money = Money::of(1_000, 'IRR');
        self::assertSame(1_000, $money->minor);
        self::assertFalse($money->isZero());
        self::assertTrue(Money::zero()->isZero());
        self::assertTrue($money->equals(Money::of(1_000)));

        try {
            Money::of(-5);
            self::fail('مبلغ منفی باید رد شود');
        } catch (\InvalidArgumentException) {
            // انتظار
        }
    }
}
