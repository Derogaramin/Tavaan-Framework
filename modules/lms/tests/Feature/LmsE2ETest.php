<?php

declare(strict_types=1);

namespace Modules\Lms\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Modules\Lms\Application\UseCases\CertificateService;
use Modules\Lms\Application\UseCases\CourseAdminService;
use Modules\Lms\Application\UseCases\EnrollmentService;
use Modules\Lms\Application\UseCases\ProgressService;
use Modules\Lms\Application\UseCases\QuizAdminService;
use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Entities\Lesson;
use Modules\Lms\Domain\Entities\Quiz;
use Modules\Lms\Domain\Entities\Section;
use Modules\Lms\Domain\Enums\AttemptStatus;
use Modules\Lms\Domain\Enums\EnrollmentStatus;
use Modules\Lms\Domain\Repositories\CertificateRepositoryInterface;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Modules\Lms\Domain\Services\CertificateSerialGenerator;
use Modules\Lms\Infrastructure\Gateways\SandboxPaymentGateway;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E ماژول LMS — سناریوهای DOC-095 §7 (E2E-01..07) + AC-LMS-08.
 *
 * مسیر واقعی HTTP (MakesHttpRequests) + SQLite in-memory + RBAC واقعی +
 * درگاه sandbox قطعی + Workflow صدور گواهی — بدون I/O خارجی.
 */
final class LmsE2ETest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private DatabaseUserRepository $users;

    private RoleManager $roles;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        SandboxPaymentGateway::resetMemory();

        $app = $this->app();
        $config = $app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('session.csrf.except', ['admin/*', 'lms/*']);
        $config->set('app.url', 'http://localhost');
        // پرداخت sandbox قطعی (بدون شبکه)
        $config->set('lms.payment.driver', 'sandbox');
        $config->set('lms.certificates.auto_issue', true);
        $config->set('lms.cache.enabled', false);
        $config->set('lms.notifications', ['welcome' => true, 'course_completed' => true, 'certificate_issued' => true, 'attempt_graded' => true]);

        $this->users = $app->make(DatabaseUserRepository::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── E2E-06: instructor دوره می‌سازد ──────────────────────────────────

    public function testE2E06_InstructorCreatesAndPublishesCourse(): void
    {
        $instructor = $this->user('ostad@lms.test', 'استاد دوره', 'instructor');

        // ۱) ساخت دورهٔ پیش‌نویس از پنل
        $response = $this->actingAs($instructor)->post('/admin/lms/courses', [
            'title' => 'آموزش رایانش ابری',
            'summary' => 'مبانی ابر',
            'level' => 'beginner',
            'is_free' => '1',
            'price' => 0,
        ]);
        $response->assertRedirect();
        \preg_match('#/admin/lms/courses/([0-9A-Z]{26})/edit#', (string) $response->header('Location'), $m);
        $courseId = $m[1] ?? '';
        self::assertNotSame('', $courseId);

        $courses = $this->app()->make(CourseRepositoryInterface::class);
        $course = $courses->findById($courseId);
        self::assertNotNull($course);
        self::assertSame('آموزش-رایانش-ابری', $course->slug());   // slug فارسی‌اول (بدون ترانویسی)
        self::assertSame($instructor->id, $course->instructorId());

        // ۲) انتشار بدون درس → رد
        $this->post('/admin/lms/courses/' . $courseId . '/publish')->assertRedirect();
        self::assertFalse($courses->findById($courseId)->isPublished());

        // ۳) بخش + درس
        $this->post('/admin/lms/courses/' . $courseId . '/sections', ['title' => 'فصل اول'])->assertRedirect();
        $structure = $courses->structureOf($courseId);
        self::assertCount(1, $structure);
        $sectionId = $structure[0]['section']->id();

        $this->post('/admin/lms/courses/' . $courseId . '/sections/' . $sectionId . '/lessons', [
            'title' => 'ابر چیست؟',
            'type' => 'text',
            'content' => 'تعریف رایانش ابری.',
            'duration' => 15,
        ])->assertRedirect();

        // ۴) انتشار → موفق
        $this->post('/admin/lms/courses/' . $courseId . '/publish')->assertRedirect();
        $course = $courses->findById($courseId);
        self::assertTrue($course->isPublished());
        self::assertSame(15, $course->totalDuration());   // مدت از درس‌ها جمع شد

        // ۵) ویترین عمومی
        $listing = $this->get('/lms');
        $listing->assertOk();
        $listing->assertSee('آموزش رایانش ابری');

        $detail = $this->get('/lms/courses/' . $course->slug());
        $detail->assertOk();
        $detail->assertSee('ابر چیست؟');   // سرفصل‌ها
        $detail->assertSee('استاد دوره');  // نام مدرس
    }

    // ─── E2E-01: ثبت‌نام در دورهٔ پولی (پرداخت) ───────────────────────────

    public function testE2E01_PaidEnrollmentWithSandboxPayment(): void
    {
        [$course, $student] = $this->courseWithStudent(price: 4_900_000);

        // ۱) ثبت‌نام → redirect به پرداخت
        $response = $this->actingAs($student)->post('/lms/courses/' . $course->id() . '/enroll');
        $response->assertRedirect();
        \preg_match('#/lms/pay/([0-9A-Z]{26})#', (string) $response->header('Location'), $m);
        $enrollmentId = $m[1] ?? '';
        self::assertNotSame('', $enrollmentId);

        $enrollments = $this->app()->make(EnrollmentRepositoryInterface::class);
        $enrollment = $enrollments->findById($enrollmentId);
        self::assertSame(EnrollmentStatus::PendingPayment, $enrollment->status());

        // ۲) شروع پرداخت → redirect به sandbox
        $payResponse = $this->get('/lms/pay/' . $enrollmentId);
        $payResponse->assertRedirect();
        \preg_match('#(/lms/pay/sandbox/sbx-[^"?]+)#', (string) $payResponse->header('Location'), $mSbx);
        self::assertArrayHasKey(1, $mSbx, 'redirect به sandbox انتظار می‌رفت');
        $sandboxUrl = $mSbx[1];

        $sandboxPage = $this->get($sandboxUrl);
        $sandboxPage->assertOk();
        $sandboxPage->assertSee('درگاه آزمایشی');
        $sandboxPage->assertSee('آموزش پیادهٔ امن');   // عنوان دوره در صفحهٔ درگاه

        // ۳) بازگشت موفق → verify با مبلغ محلی → active
        $authority = \rawurldecode(\basename($sandboxUrl));
        $result = $this->get('/lms/pay/callback?authority=' . \rawurlencode($authority) . '&status=ok');
        $result->assertOk();
        $result->assertSee('پرداخت با موفقیت انجام شد');

        $paid = $enrollments->findById($enrollmentId);
        self::assertSame(EnrollmentStatus::Active, $paid->status());
        self::assertNotNull($paid->enrolledAt());

        // student_count افزایش یافت
        $courses = $this->app()->make(CourseRepositoryInterface::class);
        self::assertSame(1, $courses->findById($course->id())->studentCount());

        // welcome notification (database) ثبت شد
        self::assertTrue(DB::table('tvn_notifications')->where('notifiable_id', (string) $student->id)->exists());

        // ۴) idempotency — callback تکراری اثر دوم ندارد
        $again = $this->get('/lms/pay/callback?authority=' . \rawurlencode($authority) . '&status=ok');
        $again->assertOk();
        self::assertSame(1, $courses->findById($course->id())->studentCount());
        self::assertSame(1, DB::table('lms_enrollments')->where('course_id', $course->id())->count());
    }

    public function testE2E01b_PaymentFailureKeepsPendingAndRetryWorks(): void
    {
        [$course, $student] = $this->courseWithStudent(price: 1_000_000);

        $this->actingAs($student)->post('/lms/courses/' . $course->id() . '/enroll')->assertRedirect();
        $enrollments = $this->app()->make(EnrollmentRepositoryInterface::class);
        $enrollment = $enrollments->find($student->id, $course->id());
        self::assertNotNull($enrollment);

        // پرداخت با simulate_failure → verify رد می‌شود
        $pay = $this->get('/lms/pay/' . $enrollment->id() . '?simulate_failure=1');
        $pay->assertRedirect();
        \preg_match('#/lms/pay/sandbox/(sbx-fail-[^"?]+)#', (string) $pay->header('Location'), $m);
        self::assertArrayHasKey(1, $m);

        $result = $this->get('/lms/pay/callback?authority=' . \rawurlencode($m[1]) . '&status=ok');
        $result->assertOk();
        $result->assertSee('پرداخت ناموفق بود');

        // هنوز pending — تلاش مجدد (بدون simulate) موفق است
        self::assertTrue($enrollments->findById($enrollment->id())->isPendingPayment());

        $retry = $this->get('/lms/pay/' . $enrollment->id());
        \preg_match('#/lms/pay/sandbox/(sbx-[^"?]+)#', (string) $retry->header('Location'), $m2);
        self::assertArrayHasKey(1, $m2);
        self::assertTrue(\str_starts_with($m2[1], 'sbx-'));
        self::assertFalse(\str_starts_with($m2[1], 'sbx-fail'));

        $this->get('/lms/pay/callback?authority=' . \rawurlencode($m2[1]) . '&status=ok')->assertOk();
        self::assertSame(EnrollmentStatus::Active, $enrollments->findById($enrollment->id())->status());
    }

    public function testE2E01c_FreeEnrollmentIsInstant(): void
    {
        [$course, $student] = $this->courseWithStudent(price: 0);

        $response = $this->actingAs($student)->post('/lms/courses/' . $course->id() . '/enroll');
        $response->assertRedirect();
        self::assertStringContainsString('/lms/learn/' . $course->slug(), (string) $response->header('Location'));

        $enrollment = $this->app()->make(EnrollmentRepositoryInterface::class)->find($student->id, $course->id());
        self::assertNotNull($enrollment);
        self::assertSame(EnrollmentStatus::Active, $enrollment->status());
        self::assertTrue(DB::table('tvn_notifications')->where('notifiable_id', (string) $student->id)->exists());

        // ثبت‌نام تکراری → همان ردیف، بدون پرداخت
        $again = $this->post('/lms/courses/' . $course->id() . '/enroll');
        $again->assertRedirect();
        self::assertSame(1, DB::table('lms_enrollments')->where('course_id', $course->id())->count());
    }

    // ─── E2E-02: مشاهدهٔ درس + پیشرفت ─────────────────────────────────────

    public function testE2E02_LessonAccessAndProgressFlow(): void
    {
        [$course, $student, $lessons] = $this->courseWithStudent(price: 0, lessonCount: 2);
        $this->enrollFree($student, $course);

        // درس اول — ۲۰۰ + محتوا
        $page = $this->actingAs($student)->get('/lms/lessons/' . $lessons[0]->id());
        $page->assertOk();
        $page->assertSee('محتوای درس');

        // تکمیل درس اول → ۵۰٪
        $this->post('/lms/lessons/' . $lessons[0]->id() . '/complete')->assertRedirect();
        $enrollments = $this->app()->make(EnrollmentRepositoryInterface::class);
        $enrollment = $enrollments->find($student->id, $course->id());
        self::assertSame(50.0, $enrollment->progressPercent());
        self::assertFalse($enrollment->isCompleted());

        // درس دوم → ۱۰۰٪ → completed
        $this->post('/lms/lessons/' . $lessons[1]->id() . '/complete')->assertRedirect();
        $enrollment = $enrollments->findById($enrollment->id());
        self::assertSame(100.0, $enrollment->progressPercent());
        self::assertTrue($enrollment->isCompleted());
        self::assertNotNull($enrollment->completedAt());

        // idempotent — تکمیل مجدد درصد را خراب نمی‌کند
        $this->post('/lms/lessons/' . $lessons[0]->id() . '/complete')->assertRedirect();
        self::assertSame(100.0, $enrollments->findById($enrollment->id())->progressPercent());
    }

    public function testE2E02b_LessonGuards(): void
    {
        [$course, $student, $lessons] = $this->courseWithStudent(price: 0, lessonCount: 2, freePreviewFirst: true);
        $stranger = $this->user('stranger@lms.test', 'غریبه', 'student');

        // درس عادی بدون ثبت‌نام → 403
        $this->actingAs($stranger)->get('/lms/lessons/' . $lessons[1]->id())->assertStatus(403);

        // درس پیش‌نمایش رایگان → ۲۰۰ (بدون ثبت‌نام)
        $this->get('/lms/lessons/' . $lessons[0]->id())->assertOk();

        // تکمیل درس بدون ثبت‌نام → 403
        $this->actingAs($stranger)->post('/lms/lessons/' . $lessons[0]->id() . '/complete')->assertStatus(403);
    }

    // ─── E2E-03: آزمون با نمره‌دهی خودکار ─────────────────────────────────

    public function testE2E03_QuizAutoGrading(): void
    {
        [$course, $student] = $this->courseWithStudent(price: 0);
        $this->enrollFree($student, $course);

        $quiz = $this->createAutoQuiz($course);

        // شروع تلاش
        $response = $this->actingAs($student)->post('/lms/quizzes/' . $quiz->id() . '/attempt');
        $response->assertRedirect();
        $attempts = $this->app()->make(QuizRepositoryInterface::class);
        $attempt = $attempts->latestAttempt($student->id, $quiz->id());
        self::assertNotNull($attempt);
        self::assertSame(AttemptStatus::InProgress, $attempt->status());

        // فرم آزمون — ۲۰۰ + سؤال‌ها
        $form = $this->get('/lms/attempts/' . $attempt->id());
        $form->assertOk();
        $form->assertSee('نخستین رئیس جمهور ایران');

        // پاسخ‌های صحیح → قبولی ۱۰۰٪
        $questions = $attempts->questionsOf($quiz->id());
        $submit = $this->post('/lms/attempts/' . $attempt->id() . '/submit', [
            'answers' => [
                $questions[0]->id() => 'b',        // mc — بنی‌صدر
                $questions[1]->id() => 'false',    // tf — غلط
            ],
        ]);
        $submit->assertRedirect();

        $graded = $attempts->findAttemptById($attempt->id());
        self::assertSame(AttemptStatus::Graded, $graded->status());
        self::assertSame(3, $graded->score());
        self::assertTrue($graded->passed());

        // کارنامه
        $resultPage = $this->get('/lms/attempts/' . $attempt->id() . '/result');
        $resultPage->assertOk();
        $resultPage->assertSee('قبول');

        // تلاش مجدد پس از قبولی → رد (flash)
        $blocked = $this->post('/lms/quizzes/' . $quiz->id() . '/attempt');
        $blocked->assertRedirect();
        self::assertSame(1, \count($attempts->attemptsOf($student->id, $quiz->id())));
    }

    public function testE2E03b_QuizFailedAndMaxAttempts(): void
    {
        [$course, $student] = $this->courseWithStudent(price: 0);
        $this->enrollFree($student, $course);

        $quiz = $this->createAutoQuiz($course, maxAttempts: 1);
        $this->actingAs($student);

        $this->post('/lms/quizzes/' . $quiz->id() . '/attempt')->assertRedirect();
        $attempts = $this->app()->make(QuizRepositoryInterface::class);
        $attempt = $attempts->latestAttempt($student->id, $quiz->id());
        $questions = $attempts->questionsOf($quiz->id());

        // پاسخ‌های غلط → رد
        $this->post('/lms/attempts/' . $attempt->id() . '/submit', [
            'answers' => [$questions[0]->id() => 'a', $questions[1]->id() => 'true'],
        ])->assertRedirect();

        $graded = $attempts->findAttemptById($attempt->id());
        self::assertSame(0, $graded->score());
        self::assertFalse($graded->passed());

        // سقف تلاش (max=1) → شروع مجدد رد می‌شود
        $this->post('/lms/quizzes/' . $quiz->id() . '/attempt')->assertRedirect();
        self::assertSame(1, \count($attempts->attemptsOf($student->id, $quiz->id())));

        // صفحهٔ آزمون «سقف تلاش» را نشان می‌دهد
        $page = $this->get('/lms/quizzes/' . $quiz->id());
        $page->assertOk();
        $page->assertSee('سقف تلاش');
    }

    public function testE2E03c_ManualGradingByInstructor(): void
    {
        $instructor = $this->user('ostad2@lms.test', 'مدرس دوم', 'instructor');
        $course = $this->makeCourse($instructor, price: 0, lessonCount: 1);
        $student = $this->user('shagerd@lms.test', 'دانشجو', 'student');
        $this->enrollFree($student, $course);

        // آزمون با سؤال تشریحی (دستی)
        $quizAdmin = $this->app()->make(QuizAdminService::class);
        $quiz = $quizAdmin->createQuiz($course->id(), ['title' => 'آزمون تشریحی', 'pass_score' => 50, 'max_attempts' => 2]);
        $quizAdmin->addQuestion($quiz, ['type' => 'true_false', 'body' => 'زمین گرد است.', 'correct_answer' => 'true', 'score' => 1]);
        $quizAdmin->addQuestion($quiz, ['type' => 'descriptive', 'body' => 'معماری شش‌ضلعی را توضیح دهید.', 'score' => 3]);

        $this->actingAs($student);
        $this->post('/lms/quizzes/' . $quiz->id() . '/attempt')->assertRedirect();
        $attempts = $this->app()->make(QuizRepositoryInterface::class);
        $attempt = $attempts->latestAttempt($student->id, $quiz->id());
        $questions = $attempts->questionsOf($quiz->id());

        $this->post('/lms/attempts/' . $attempt->id() . '/submit', [
            'answers' => [
                $questions[0]->id() => 'true',
                $questions[1]->id() => 'پورت/آداپتر — دامنه مستقل از زیرساخت.',
            ],
        ])->assertRedirect();

        // submitted — در انتظار نمرهٔ دستی؛ passed هنوز null
        $submitted = $attempts->findAttemptById($attempt->id());
        self::assertSame(AttemptStatus::Submitted, $submitted->status());
        self::assertNull($submitted->passed());
        self::assertSame(1, $submitted->score());   // فقط خودکار

        // صف نمره‌دهی مدرس
        $queue = $this->actingAs($instructor)->get('/admin/lms/grading');
        $queue->assertOk();
        $queue->assertSee('دانشجو');

        $form = $this->get('/admin/lms/grading/' . $attempt->id());
        $form->assertOk();
        $form->assertSee('پورت/آداپتر');

        // نمرهٔ دستی ۲/۳ → مجموع ۳/۴ = ۷۵٪ ≥ ۵۰٪ → قبول
        $this->post('/admin/lms/grading/' . $attempt->id(), [
            'grades' => [$questions[1]->id() => ['score' => 2, 'note' => 'پاسخ خوب']],
        ])->assertRedirect();

        $graded = $attempts->findAttemptById($attempt->id());
        self::assertSame(AttemptStatus::Graded, $graded->status());
        self::assertSame(3, $graded->score());
        self::assertTrue($graded->passed());
        self::assertSame($instructor->id, $graded->gradedBy());

        // اعلان نتیجه برای دانشجو (database)
        self::assertTrue(DB::table('tvn_notifications')
            ->where('notifiable_id', (string) $student->id)
            ->where('data', 'LIKE', '%lms.attempt.graded%')
            ->exists());
    }

    public function testQuizTimeLimitRejectsLateSubmit(): void
    {
        [$course, $student] = $this->courseWithStudent(price: 0);
        $this->enrollFree($student, $course);

        $quiz = $this->createAutoQuiz($course, timeLimit: 10);
        $this->actingAs($student);
        $this->post('/lms/quizzes/' . $quiz->id() . '/attempt')->assertRedirect();

        $attempts = $this->app()->make(QuizRepositoryInterface::class);
        $attempt = $attempts->latestAttempt($student->id, $quiz->id());

        // started_at را ۲ ساعت به گذشته می‌بریم (فراتر از time_limit=10min)
        DB::table('lms_quiz_attempts')->where('id', $attempt->id())->update([
            'started_at' => (new \DateTimeImmutable('-2 hours', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);

        $questions = $attempts->questionsOf($quiz->id());
        $this->post('/lms/attempts/' . $attempt->id() . '/submit', [
            'answers' => [$questions[0]->id() => 'b'],
        ])->assertRedirect();

        // رد شد — همچنان in_progress
        self::assertSame(AttemptStatus::InProgress, $attempts->findAttemptById($attempt->id())->status());
    }

    // ─── E2E-04: تکمیل دوره → صدور گواهی (Workflow) ────────────────────────

    public function testE2E04_CourseCompletionIssuesCertificateViaWorkflow(): void
    {
        $instructor = $this->user('ostad3@lms.test', 'مدرس سوم', 'instructor');
        $course = $this->makeCourse($instructor, price: 0, lessonCount: 1, withQuiz: true);
        $student = $this->user('daneshjo3@lms.test', 'دانشجوی سوم', 'student');
        $this->enrollFree($student, $course);
        $this->actingAs($student);

        $courses = $this->app()->make(CourseRepositoryInterface::class);
        $lessons = $courses->lessonsOf($course->id());
        $quizLesson = null;
        foreach ($lessons as $lesson) {
            if ($lesson->quizId() !== null) {
                $quizLesson = $lesson;
            }
        }
        self::assertNotNull($quizLesson, 'درس آزمون‌دار ساخته نشد');

        // ۱) آزمون را قبول می‌شویم
        $quiz = $this->app()->make(QuizRepositoryInterface::class)->findQuizById((string) $quizLesson->quizId());
        self::assertNotNull($quiz);
        $this->post('/lms/quizzes/' . $quiz->id() . '/attempt')->assertRedirect();
        $attempts = $this->app()->make(QuizRepositoryInterface::class);
        $attempt = $attempts->latestAttempt($student->id, $quiz->id());
        $questions = $attempts->questionsOf($quiz->id());
        $answers = [];
        foreach ($questions as $q) {
            $answers[$q->id()] = $q->correctAnswer() ?? 'true';
        }
        $this->post('/lms/attempts/' . $attempt->id() . '/submit', ['answers' => $answers])->assertRedirect();
        self::assertTrue($attempts->findAttemptById($attempt->id())->passed());

        // هنوز گواهی صادر نشده — درسِ آزمون با قبولی تکمیل شد ولی درس معمولی باقی است
        $certificates = $this->app()->make(CertificateRepositoryInterface::class);
        $enrollments = $this->app()->make(EnrollmentRepositoryInterface::class);
        $enrollment = $enrollments->find($student->id, $course->id());
        self::assertNull($certificates->findForEnrollment($enrollment->id()));

        // ۲) درس معمولی تکمیل → دوره کامل → گواهی خودکار
        $regular = $lessons[0]->quizId() === null ? $lessons[0] : $lessons[1];
        $this->post('/lms/lessons/' . $regular->id() . '/complete')->assertRedirect();

        $enrollment = $enrollments->findById($enrollment->id());
        self::assertTrue($enrollment->isCompleted());

        $certificate = $certificates->findForEnrollment($enrollment->id());
        self::assertNotNull($certificate, 'گواهی باید خودکار صادر می‌شد');
        self::assertTrue($certificate->isValid());

        // serial با قالب + رقم کنترل صحیح (AC-LMS-08)
        $serials = new CertificateSerialGenerator('TVN-LMS');
        self::assertTrue($serials->isValidFormat($certificate->serial()));

        // Workflow — نمونهٔ فرایند ثبت و تا issued پیش رفته است
        self::assertNotNull($certificate->workflowInstanceId());
        self::assertNotSame('', (string) $certificate->workflowInstanceId());
        $wfRow = DB::table('tvn_process_instances')->where('id', $certificate->workflowInstanceId())->first();
        self::assertNotNull($wfRow, 'نمونهٔ workflow در DB نیست');

        // verify_url + اعلان صدور
        self::assertSame('/lms/certificates/' . $certificate->serial(), $certificate->verifyUrl());
        self::assertTrue(DB::table('tvn_notifications')
            ->where('notifiable_id', (string) $student->id)
            ->where('data', 'LIKE', '%lms.certificate.issued%')
            ->exists());

        // idempotency — تکمیل مجدد درس، گواهی دوم نمی‌سازد
        $this->post('/lms/lessons/' . $regular->id() . '/complete')->assertRedirect();
        self::assertSame(1, DB::table('lms_certificates')->where('enrollment_id', $enrollment->id())->count());
    }

    // ─── E2E-05: verify مدرک با serial ─────────────────────────────────────

    public function testE2E05_CertificateVerifyPublicAndRevoke(): void
    {
        [$course, $student] = $this->courseWithStudent(price: 0, lessonCount: 1);
        $this->enrollFree($student, $course);
        $this->actingAs($student);

        // تکمیل دوره (بدون آزمون → گواهی فوری)
        $courses = $this->app()->make(CourseRepositoryInterface::class);
        $lessons = $courses->lessonsOf($course->id());
        $this->post('/lms/lessons/' . $lessons[0]->id() . '/complete')->assertRedirect();

        $enrollment = $this->app()->make(EnrollmentRepositoryInterface::class)->find($student->id, $course->id());
        $certificate = $this->app()->make(CertificateRepositoryInterface::class)->findForEnrollment($enrollment->id());
        self::assertNotNull($certificate);
        $serial = $certificate->serial();

        // ۱) verify عمومی (بدون لاگین) — معتبر
        $verify = $this->get('/lms/certificates/' . $serial);
        $verify->assertOk();
        $verify->assertSee('این گواهی معتبر است');
        $verify->assertSee($serial);
        $verify->assertSee('دانشجوی آزمایش');

        // ۲) سند چاپ — عمومی + شامل نام/دوره/serial
        $document = $this->get('/lms/certificates/' . $serial . '/document');
        $document->assertOk();
        $document->assertSee('گواهی پایان دوره');
        $document->assertSee('دانشجوی آزمایش');
        $document->assertSee($course->title());

        // ۳) serial دستکاری‌شده — رقم کنترل رد می‌کند (بدون nشت DB)
        $tampered = $serial;
        $tampered[8] = $tampered[8] === 'A' ? 'B' : 'A';
        $bad = $this->get('/lms/certificates/' . $tampered);
        $bad->assertOk();
        $bad->assertSee('یافت نشد');

        // serial با قالب درست ولی ناموجود
        $ghost = (new CertificateSerialGenerator())->generate();
        $this->get('/lms/certificates/' . $ghost)->assertOk()->assertSee('یافت نشد');

        // ۴) ابطال مدیرتی → verify «باطل» + سند 404
        $admin = $this->user('admin2@lms.test', 'مدیر', 'lms-admin');
        $this->actingAs($admin)->post('/admin/lms/certificates/' . $certificate->id() . '/revoke')->assertRedirect();

        $revoked = $this->get('/lms/certificates/' . $serial);
        $revoked->assertOk();
        $revoked->assertSee('باطل');

        $this->get('/lms/certificates/' . $serial . '/document')->assertStatus(404);
    }

    // ─── E2E-07: گزارش پیشرفت ─────────────────────────────────────────────

    public function testE2E07_ProgressReportAndCsv(): void
    {
        $instructor = $this->user('ostad4@lms.test', 'مدرس چهارم', 'instructor');
        $course = $this->makeCourse($instructor, price: 0, lessonCount: 2);
        $student = $this->user('daneshjo4@lms.test', 'دانشجوی چهارم', 'student');
        $this->enrollFree($student, $course);

        $this->actingAs($student);
        $lessons = $this->app()->make(CourseRepositoryInterface::class)->lessonsOf($course->id());
        $this->post('/lms/lessons/' . $lessons[0]->id() . '/complete')->assertRedirect();

        $admin = $this->user('admin3@lms.test', 'مدیر سه', 'lms-admin');

        $report = $this->actingAs($admin)->get('/admin/lms/reports/progress?course_id=' . $course->id());
        $report->assertOk();
        $report->assertSee('گزارش پیشرفت');
        $report->assertSee('دانشجوی چهارم');
        $report->assertSee($course->title());

        $csv = $this->get('/admin/lms/reports/progress.csv');
        $csv->assertOk();
        self::assertStringContainsString('text/csv', (string) $csv->header('Content-Type'));
        $body = $csv->content();
        self::assertStringContainsString($course->slug(), $body);
        self::assertTrue(\str_starts_with($body, "\xEF\xBB\xBF"), 'CSV باید BOM داشته باشد');

        // دانشجو دسترسی به گزارش ندارد
        $this->actingAs($student)->get('/admin/lms/reports/progress')->assertStatus(403);
    }

    // ─── نقش‌ها و دسترسی‌ها ────────────────────────────────────────────────

    public function testRoleMatrix_PanelAccess(): void
    {
        [$course, $student, , $owner] = $this->courseWithStudent(price: 0);

        // دانشجو → پنل ۴۰۳
        $this->actingAs($student)->get('/admin/lms/courses')->assertStatus(403);
        $this->get('/admin/lms/enrollments')->assertStatus(403);
        $this->get('/admin/lms/certificates')->assertStatus(403);

        // مدرس دیگر → ویرایش دورهٔ غیرخود ۴۰۳
        $other = $this->user('ostad5@lms.test', 'مدرس دیگر', 'instructor');
        $this->actingAs($other)->get('/admin/lms/courses/' . $course->id() . '/edit')->assertStatus(403);
        $this->post('/admin/lms/courses/' . $course->id() . '/publish')->assertStatus(403);

        // مدرس → فهرست پنل فقط دوره‌های خودش
        $panel = $this->get('/admin/lms/courses');
        $panel->assertOk();
        $panel->assertDontSee($course->title());

        $ownerPanel = $this->actingAs($owner)->get('/admin/lms/courses');
        $ownerPanel->assertOk();
        $ownerPanel->assertSee($course->title());

        // lms-admin → همه
        $admin = $this->user('admin4@lms.test', 'مدیر چهار', 'lms-admin');
        $this->actingAs($admin)->get('/admin/lms/courses')->assertOk()->assertSee($course->title());
    }

    public function testAssistantCanGradeOtherInstructorsQuiz(): void
    {
        $instructor = $this->user('ostad6@lms.test', 'مدرس ششم', 'instructor');
        $course = $this->makeCourse($instructor, price: 0, lessonCount: 1);
        $student = $this->user('daneshjo6@lms.test', 'دانشجوی ششم', 'student');
        $this->enrollFree($student, $course);

        $quizAdmin = $this->app()->make(QuizAdminService::class);
        $quiz = $quizAdmin->createQuiz($course->id(), ['title' => 'آزمون دستی', 'pass_score' => 50]);
        $quizAdmin->addQuestion($quiz, ['type' => 'descriptive', 'body' => 'سؤال تشریحی', 'score' => 2]);

        $this->actingAs($student);
        $this->post('/lms/quizzes/' . $quiz->id() . '/attempt')->assertRedirect();
        $attempts = $this->app()->make(QuizRepositoryInterface::class);
        $attempt = $attempts->latestAttempt($student->id, $quiz->id());
        $questions = $attempts->questionsOf($quiz->id());
        $this->post('/lms/attempts/' . $attempt->id() . '/submit', [
            'answers' => [$questions[0]->id() => 'پاسخ'],
        ])->assertRedirect();

        // assistant (grade-any) → نمره‌دهی مجاز
        $assistant = $this->user('dastyar@lms.test', 'دستیار', 'assistant');
        $this->actingAs($assistant)->get('/admin/lms/grading')->assertOk()->assertSee('دانشجوی ششم');
        $this->post('/admin/lms/grading/' . $attempt->id(), [
            'grades' => [$questions[0]->id() => ['score' => 2, 'note' => '']],
        ])->assertRedirect();

        self::assertTrue($attempts->findAttemptById($attempt->id())->passed());

        // دانشجو نمی‌تواند نمره بدهد
        $this->actingAs($student)->get('/admin/lms/grading')->assertStatus(403);
    }

    // ─── پیش‌نیاز ─────────────────────────────────────────────────────────

    public function testPrerequisitesBlockEnrollment(): void
    {
        $instructor = $this->user('ostad7@lms.test', 'مدرس هفتم', 'instructor');
        $courseA = $this->makeCourse($instructor, price: 0, lessonCount: 1, title: 'پیش‌نیاز الف');
        $courseB = $this->makeCourse($instructor, price: 0, lessonCount: 1, title: 'دورهٔ پیشرفته ب');

        // B پیش‌نیاز A دارد
        $adminService = $this->app()->make(CourseAdminService::class);
        $adminService->update($courseB, [
            'title' => $courseB->title(),
            'level' => 'advanced',
            'price' => 0,
            'is_free' => true,
            'prerequisites' => [$courseA->id()],
        ]);

        $student = $this->user('daneshjo7@lms.test', 'دانشجوی هفتم', 'student');
        $this->actingAs($student);

        // ثبت‌نام در B بدون تکمیل A → رد با flash
        $this->post('/lms/courses/' . $courseB->id() . '/enroll')->assertRedirect();
        $enrollments = $this->app()->make(EnrollmentRepositoryInterface::class);
        self::assertNull($enrollments->find($student->id, $courseB->id()));

        // صفحهٔ دوره پیش‌نیاز را نشان می‌دهد
        $this->get('/lms/courses/' . $courseB->slug())->assertOk()->assertSee('پیش‌نیاز الف');

        // A را کامل می‌کنیم → B باز می‌شود
        $this->enrollFree($student, $courseA);
        $lessons = $this->app()->make(CourseRepositoryInterface::class)->lessonsOf($courseA->id());
        $this->post('/lms/lessons/' . $lessons[0]->id() . '/complete')->assertRedirect();

        $this->post('/lms/courses/' . $courseB->id() . '/enroll')->assertRedirect();
        $enrollment = $enrollments->find($student->id, $courseB->id());
        self::assertNotNull($enrollment);
        self::assertSame(EnrollmentStatus::Active, $enrollment->status());
    }

    // ─── امنیت ────────────────────────────────────────────────────────────

    public function testXssSanitizedInLessonAndQa(): void
    {
        $instructor = $this->user('ostad8@lms.test', 'مدرس هشتم', 'instructor');
        $course = $this->makeCourse($instructor, price: 0, lessonCount: 1);
        $student = $this->user('daneshjo8@lms.test', 'دانشجوی هشتم', 'student');
        $this->enrollFree($student, $course);

        // درس با اسکریپت مخرب (از راه سرویس ادمین — Markdown+sanitize)
        $courses = $this->app()->make(CourseRepositoryInterface::class);
        $structure = $courses->structureOf($course->id());
        $sectionId = $structure[0]['section']->id();

        $this->actingAs($instructor);
        $this->post('/admin/lms/courses/' . $course->id() . '/sections/' . $sectionId . '/lessons', [
            'title' => 'درس خطرناک',
            'type' => 'text',
            'content' => "متن سالم\n\n<script>alert('xss')</script>\n\n<img src=x onerror=\"alert(2)\">",
        ])->assertRedirect();

        $lessons = $courses->lessonsOf($course->id());
        $dangerous = null;
        foreach ($lessons as $lesson) {
            if ($lesson->title() === 'درس خطرناک') {
                $dangerous = $lesson;
            }
        }
        self::assertNotNull($dangerous);

        $page = $this->actingAs($student)->get('/lms/lessons/' . $dangerous->id());
        $page->assertOk();
        $content = $page->content();
        self::assertStringNotContainsString('<script>alert', $content);
        self::assertStringNotContainsString('<img', $content);
        self::assertStringNotContainsString('onerror="', $content);
        self::assertStringContainsString('متن سالم', $content);

        // Q&A — بدنهٔ پرسش sanitize + escape
        $first = $lessons[0];
        $this->post('/lms/lessons/' . $first->id() . '/questions', [
            'body' => '<script>alert(3)</script>سؤال من',
        ])->assertRedirect();

        // پس از تأیید، صفحه درس اسکریپت خام ندارد
        DB::table('lms_comments')->where('commentable_id', $first->id())->update(['status' => 'approved']);
        $lessonPage = $this->get('/lms/lessons/' . $first->id());
        $lessonPage->assertOk();
        self::assertStringNotContainsString('<script>alert(3)</script>', $lessonPage->content());
    }

    public function testQnaStudentPendingStaffApproved(): void
    {
        $instructor = $this->user('ostad9@lms.test', 'مدرس نهم', 'instructor');
        $course = $this->makeCourse($instructor, price: 0, lessonCount: 1);
        $student = $this->user('daneshjo9@lms.test', 'دانشجوی نهم', 'student');
        $this->enrollFree($student, $course);

        $lessons = $this->app()->make(CourseRepositoryInterface::class)->lessonsOf($course->id());
        $lesson = $lessons[0];

        // پرسش دانشجو → در صف moderation (pending)
        $this->actingAs($student)->post('/lms/lessons/' . $lesson->id() . '/questions', [
            'body' => 'سؤال اول دانشجو',
        ])->assertRedirect();

        $row = DB::table('lms_comments')->where('commentable_id', $lesson->id())->first();
        self::assertNotNull($row);
        self::assertSame('pending', (string) $row['status']);

        // پاسخ مدرس (staff = مالک دوره) → بدون ثبت‌نام و بدون moderation منتشر می‌شود
        $this->actingAs($instructor);
        $this->post('/lms/lessons/' . $lesson->id() . '/questions', [
            'body' => 'پاسخ مدرس به سؤال',
            'parent_id' => (string) $row['id'],
        ])->assertRedirect();

        self::assertTrue(DB::table('lms_comments')
            ->where('commentable_id', $lesson->id())
            ->where('status', 'approved')
            ->exists());
    }

    // ─── جست‌وجوی فارسی ───────────────────────────────────────────────────

    public function testPersianSearchWithDigitVariants(): void
    {
        $instructor = $this->user('ostad10@lms.test', 'مدرس دهم', 'instructor');
        $this->makeCourse($instructor, price: 0, lessonCount: 1, title: 'ریاضی ۱۴۰۵');

        // عنوان با رقم فارسی ذخیره شده — جست‌وجو با رقم لاتین هم می‌زند (fallback LIKE)
        $this->get('/lms/search?q=' . \rawurlencode('ریاضی 1405'))->assertOk()->assertSee('ریاضی ۱۴۰۵');
        $this->get('/lms/search?q=' . \rawurlencode('ریاضی ۱۴۰۵'))->assertOk()->assertSee('ریاضی ۱۴۰۵');
        $this->get('/lms/search?q=' . \rawurlencode('نادیده‌گرفته'))->assertOk()->assertSee('نتیجه‌ای یافت نشد');
    }

    public function testEnrollAndLearnRequireAuth(): void
    {
        [$course] = $this->courseWithStudent(price: 0);

        // مهمان → redirect (AuthenticateMiddleware)
        $this->post('/lms/courses/' . $course->id() . '/enroll')->assertRedirect();
        $this->get('/lms/learn')->assertRedirect();
    }

    // ─── helperها ─────────────────────────────────────────────────────────

    private function seedRbac(): void
    {
        $permissions = [
            'lms.course.view', 'lms.course.create', 'lms.course.update', 'lms.course.delete', 'lms.course.publish',
            'lms.category.manage', 'lms.enrollment.view', 'lms.enrollment.manage', 'lms.quiz.manage',
            'lms.attempt.grade', 'lms.attempt.grade-any', 'lms.certificate.view', 'lms.certificate.revoke',
            'lms.report.view',
        ];
        foreach ($permissions as $key) {
            if (!$this->permissionExists($key)) {
                $this->roles->createPermission($key, 'tavaan.lms');
            }
        }

        $rolePerms = [
            'student' => [],
            'instructor' => ['lms.course.create', 'lms.quiz.manage', 'lms.attempt.grade', 'lms.enrollment.view', 'lms.certificate.view', 'lms.report.view'],
            'assistant' => ['lms.attempt.grade', 'lms.attempt.grade-any', 'lms.enrollment.view', 'lms.course.view'],
            'lms-admin' => $permissions,
            'admin' => $permissions,
        ];

        foreach ($rolePerms as $roleKey => $perms) {
            if ($this->roles->findRole($roleKey) === null) {
                $this->roles->create($roleKey, $roleKey);
            }
            foreach ($perms as $permission) {
                $this->roles->grantPermissionToRole($roleKey, $permission);
            }
        }
    }

    private function permissionExists(string $key): bool
    {
        return DB::table('tvn_permissions')->where('key', $key)->exists();
    }

    private function user(string $email, string $name, string $role = 'student'): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }

    /**
     * دورهٔ منتشرشده با ساختار کامل + کاربر دانشجو.
     *
     * @return array{0: Course, 1: User, 2: list<Lesson>, 3: User}
     */
    private function courseWithStudent(int $price = 0, int $lessonCount = 2, bool $freePreviewFirst = false, ?string $title = null): array
    {
        $instructor = $this->user('ostad-' . \bin2hex(\random_bytes(3)) . '@lms.test', 'مدرس آزمایش', 'instructor');
        $course = $this->makeCourse($instructor, $price, $lessonCount, freePreviewFirst: $freePreviewFirst, title: $title);
        $student = $this->user('daneshjo-' . \bin2hex(\random_bytes(3)) . '@lms.test', 'دانشجوی آزمایش', 'student');

        return [$course, $student, $this->app()->make(CourseRepositoryInterface::class)->lessonsOf($course->id()), $instructor];
    }

    /** دورهٔ منتشرشده با بخش/درس (و آزمون اختیاری) از راه سرویس‌های واقعی. */
    private function makeCourse(User $instructor, int $price = 0, int $lessonCount = 1, bool $withQuiz = false, bool $freePreviewFirst = false, ?string $title = null, string $level = 'beginner'): Course
    {
        $admin = $this->app()->make(CourseAdminService::class);
        $title ??= 'آموزش پیادهٔ امن ' . \substr(\bin2hex(\random_bytes(2)), 0, 4);

        $course = $admin->create([
            'title' => $title,
            'summary' => 'خلاصهٔ ' . $title,
            'description' => 'توضیحات ' . $title,
            'level' => $level,
            'price' => $price,
            'is_free' => $price === 0,
        ], $instructor->id);

        if ($lessonCount > 0 || $withQuiz) {
            $section = $admin->addSection($course, 'بخش نخست');

            for ($i = 1; $i <= $lessonCount; $i++) {
                $admin->addLesson($course, $section, [
                    'title' => 'درس ' . $i,
                    'type' => 'text',
                    'content' => 'محتوای درس ' . $i,
                    'duration' => 10,
                    'is_free_preview' => $freePreviewFirst && $i === 1,
                ]);
            }

            if ($withQuiz) {
                $quizAdmin = $this->app()->make(QuizAdminService::class);
                $quiz = $quizAdmin->createQuiz($course->id(), ['title' => 'آزمون پایانی', 'pass_score' => 50, 'max_attempts' => 3]);
                $quizAdmin->addQuestion($quiz, ['type' => 'true_false', 'body' => 'تَوان صفر‌وابستگی است.', 'correct_answer' => 'true', 'score' => 2]);

                $admin->addLesson($course, $section, [
                    'title' => 'آزمون پایان دوره',
                    'type' => 'quiz',
                    'quiz_id' => $quiz->id(),
                    'duration' => 5,
                ]);
            }

            $admin->publish($course);
        } else {
            $course = $this->app()->make(CourseRepositoryInterface::class)->findById($course->id()) ?? $course;
        }

        return $this->app()->make(CourseRepositoryInterface::class)->findById($course->id()) ?? $course;
    }

    /** آزمون خودکارنمره با ۲ سؤال (mc + tf — جمع ۳ نمره). */
    private function createAutoQuiz(Course $course, int $maxAttempts = 3, ?int $timeLimit = null): Quiz
    {
        $quizAdmin = $this->app()->make(QuizAdminService::class);
        $quiz = $quizAdmin->createQuiz($course->id(), [
            'title' => 'آزمون خودکار',
            'pass_score' => 50,
            'max_attempts' => $maxAttempts,
            'time_limit' => $timeLimit,
        ]);

        $quizAdmin->addQuestion($quiz, [
            'type' => 'multiple_choice',
            'body' => 'نخستین رئیس جمهور ایران چه کسی بود؟',
            'options' => "a=رجوی\nb=بنی‌صدر\nc=خامنه‌ای",
            'correct_answer' => 'b',
            'score' => 2,
        ]);
        $quizAdmin->addQuestion($quiz, [
            'type' => 'true_false',
            'body' => 'PHP یک زبان کامپایلری است.',
            'correct_answer' => 'false',
            'score' => 1,
        ]);

        return $quiz;
    }

    private function enrollFree(User $student, Course $course): void
    {
        $service = $this->app()->make(EnrollmentService::class);
        $result = $service->enroll($student->id, $course);
        self::assertFalse($result['needsPayment'], 'دوره باید رایگان باشد');
    }

    /** پیشرفت دستی — بدون HTTP (برای سناریوهای پیش‌نیاز). */
    private function completeAllLessons(User $student, Course $course): void
    {
        $progress = $this->app()->make(ProgressService::class);
        $enrollment = $this->app()->make(EnrollmentRepositoryInterface::class)->find($student->id, $course->id());
        self::assertNotNull($enrollment);

        foreach ($this->app()->make(CourseRepositoryInterface::class)->lessonsOf($course->id()) as $lesson) {
            $progress->markLessonComplete($enrollment, $lesson);
        }
    }
}
