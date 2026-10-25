<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * LmsServiceProvider — bindingها + routes/lang + listenerها.
 */

namespace Modules\Lms\Providers;

use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\Content\Domain\Services\Markdown;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Lms\Application\UseCases\CatalogService;
use Modules\Lms\Application\UseCases\CertificateService;
use Modules\Lms\Application\UseCases\CourseAdminService;
use Modules\Lms\Application\UseCases\EnrollmentService;
use Modules\Lms\Application\UseCases\ProgressService;
use Modules\Lms\Application\UseCases\QuizAdminService;
use Modules\Lms\Application\UseCases\QuizService;
use Modules\Lms\Application\UseCases\ReportService;
use Modules\Lms\Contracts\PaymentGatewayPort;
use Modules\Lms\Domain\Repositories\CertificateRepositoryInterface;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Lms\Domain\Repositories\QuizRepositoryInterface;
use Modules\Lms\Domain\Services\AnswerNormalizer;
use Modules\Lms\Domain\Services\CertificateSerialGenerator;
use Modules\Lms\Domain\Services\PrerequisiteChecker;
use Modules\Lms\Domain\Services\ProgressCalculator;
use Modules\Lms\Domain\Services\QuizGrader;
use Modules\Lms\Infrastructure\Gateways\EnginePaymentGateway;
use Modules\Lms\Infrastructure\Gateways\SandboxPaymentGateway;
use Modules\Lms\Infrastructure\Notifications\LmsNotifier;
use Modules\Lms\Infrastructure\Persistence\DatabaseCertificateRepository;
use Modules\Lms\Infrastructure\Persistence\DatabaseCourseRepository;
use Modules\Lms\Infrastructure\Persistence\DatabaseEnrollmentRepository;
use Modules\Lms\Infrastructure\Persistence\DatabaseLmsCommentStore;
use Modules\Lms\Infrastructure\Persistence\DatabaseQuizRepository;
use Modules\Lms\Infrastructure\Search\CourseSearchIndexer;
use Modules\Lms\Infrastructure\Workflow\CertificateWorkflow;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول LMS
 *
 * 🔒 ARCH-003: فقط Core + Contracts ماژول‌های هم‌خانواده + SDK مجاز.
 * Policyها از راه config اعلامی (modules/lms/config/rbac.php).
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class LmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = fn (string $key = 'lms'): array => $this->app->bound('config')
            ? (array) $this->app->make('config')->get($key, [])
            : [];

        // ── Infrastructure: مخزن‌ها ──────────────────────────────────────
        $this->app->singleton(CourseRepositoryInterface::class, static fn (): CourseRepositoryInterface => new DatabaseCourseRepository());
        $this->app->singleton(EnrollmentRepositoryInterface::class, static fn (): EnrollmentRepositoryInterface => new DatabaseEnrollmentRepository());
        $this->app->singleton(QuizRepositoryInterface::class, static fn (): QuizRepositoryInterface => new DatabaseQuizRepository());
        $this->app->singleton(CertificateRepositoryInterface::class, static fn (): CertificateRepositoryInterface => new DatabaseCertificateRepository());
        $this->app->singleton(DatabaseLmsCommentStore::class, static fn (): DatabaseLmsCommentStore => new DatabaseLmsCommentStore());

        // ── Domain Services (خالص) ────────────────────────────────────────
        $this->app->singleton(ProgressCalculator::class, static fn (): ProgressCalculator => new ProgressCalculator());
        $this->app->singleton(PrerequisiteChecker::class, static fn (): PrerequisiteChecker => new PrerequisiteChecker());
        $this->app->singleton(AnswerNormalizer::class, static fn (): AnswerNormalizer => new AnswerNormalizer());
        $this->app->singleton(QuizGrader::class, fn (ContainerInterface $c): QuizGrader => new QuizGrader(
            $c->make(AnswerNormalizer::class),
            (bool) ($config()['quiz']['auto_grade_short_answer'] ?? true),
        ));
        $this->app->singleton(CertificateSerialGenerator::class, fn (): CertificateSerialGenerator => new CertificateSerialGenerator(
            (string) ($config()['certificates']['serial_prefix'] ?? 'TVN-LMS'),
        ));

        // ── درگاه پرداخت (driver از config — الگوی shop) ──────────────────
        $this->app->singleton(PaymentGatewayPort::class, function () use ($config): PaymentGatewayPort {
            $payment = (array) ($config()['payment'] ?? []);
            $driver = (string) ($payment['driver'] ?? 'sandbox');

            return $driver === 'engine'
                ? new EnginePaymentGateway((string) ($payment['gateway'] ?? ''))
                : new SandboxPaymentGateway();
        });
        $this->app->alias(PaymentGatewayPort::class, 'lms.payment');

        // ── Workflow صدور گواهی ───────────────────────────────────────────
        $this->app->singleton(CertificateWorkflow::class, fn (): CertificateWorkflow => new CertificateWorkflow(
            (string) ($config()['certificates']['workflow'] ?? 'lms-issue-certificate'),
        ));

        // ── Application: UseCaseها ────────────────────────────────────────
        $this->app->singleton(CatalogService::class, fn (ContainerInterface $c): CatalogService => new CatalogService(
            courses: $c->make(CourseRepositoryInterface::class),
            users: $c->make(UserReaderInterface::class),
            config: $config(),
        ));

        $this->app->singleton(CourseAdminService::class, fn (ContainerInterface $c): CourseAdminService => new CourseAdminService(
            courses: $c->make(CourseRepositoryInterface::class),
            markdown: $c->make(Markdown::class),
            prerequisites: $c->make(PrerequisiteChecker::class),
            config: $config(),
        ));

        $this->app->singleton(QuizAdminService::class, fn (ContainerInterface $c): QuizAdminService => new QuizAdminService(
            quizzes: $c->make(QuizRepositoryInterface::class),
            courses: $c->make(CourseRepositoryInterface::class),
            config: (array) ($config()['quiz'] ?? []),
        ));

        $this->app->singleton(EnrollmentService::class, fn (ContainerInterface $c): EnrollmentService => new EnrollmentService(
            enrollments: $c->make(EnrollmentRepositoryInterface::class),
            courses: $c->make(CourseRepositoryInterface::class),
            prerequisites: $c->make(PrerequisiteChecker::class),
            gateway: $c->make(PaymentGatewayPort::class),
            config: $config(),
        ));

        $this->app->singleton(CertificateService::class, fn (ContainerInterface $c): CertificateService => new CertificateService(
            certificates: $c->make(CertificateRepositoryInterface::class),
            enrollments: $c->make(EnrollmentRepositoryInterface::class),
            courses: $c->make(CourseRepositoryInterface::class),
            quizzes: $c->make(QuizRepositoryInterface::class),
            users: $c->make(UserReaderInterface::class),
            serials: $c->make(CertificateSerialGenerator::class),
            workflow: $c->make(CertificateWorkflow::class),
            config: (array) ($config()['certificates'] ?? []),
        ));

        $this->app->singleton(ProgressService::class, fn (ContainerInterface $c): ProgressService => new ProgressService(
            enrollments: $c->make(EnrollmentRepositoryInterface::class),
            courses: $c->make(CourseRepositoryInterface::class),
            quizzes: $c->make(QuizRepositoryInterface::class),
            calculator: $c->make(ProgressCalculator::class),
            certificates: $c->make(CertificateService::class),
            config: $config(),
        ));

        $this->app->singleton(QuizService::class, fn (ContainerInterface $c): QuizService => new QuizService(
            quizzes: $c->make(QuizRepositoryInterface::class),
            enrollments: $c->make(EnrollmentRepositoryInterface::class),
            grader: $c->make(QuizGrader::class),
            progress: $c->make(ProgressService::class),
            config: $config(),
        ));

        $this->app->singleton(ReportService::class, fn (ContainerInterface $c): ReportService => new ReportService(
            courses: $c->make(CourseRepositoryInterface::class),
            enrollments: $c->make(EnrollmentRepositoryInterface::class),
            quizzes: $c->make(QuizRepositoryInterface::class),
            users: $c->make(UserReaderInterface::class),
        ));

        // ── Infrastructure: شنونده‌ها ─────────────────────────────────────
        $this->app->singleton(CourseSearchIndexer::class, fn (ContainerInterface $c): CourseSearchIndexer => new CourseSearchIndexer(
            courses: $c->make(CourseRepositoryInterface::class),
            config: (array) ($config()['search'] ?? []),
        ));

        $this->app->singleton(LmsNotifier::class, fn (ContainerInterface $c): LmsNotifier => new LmsNotifier(
            users: $c->make(UserReaderInterface::class),
            container: $c,
            config: (array) ($config()['notifications'] ?? []),
        ));

        // پرسش‌وپاسخ درس‌ها — مصرف‌کنندهٔ ویوها (ماژول comment)
        $this->app->singleton('lms.qa.thread', static fn (ContainerInterface $c): CommentThreadBuilder => $c->make(CommentThreadBuilder::class));
        $this->app->singleton('lms.qa.moderator', static fn (ContainerInterface $c): CommentModerator => $c->make(CommentModerator::class));

        // Markdown برای بدنهٔ درس (ویوهای admin هم مصرف می‌کنند)
        $this->app->singleton('lms.markdown', static fn (ContainerInterface $c): Markdown => $c->make(Markdown::class));
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        $this->registerLang($moduleRoot);
        $this->registerRoutes($moduleRoot);
        $this->registerListeners();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            CourseRepositoryInterface::class,
            EnrollmentRepositoryInterface::class,
            QuizRepositoryInterface::class,
            CertificateRepositoryInterface::class,
            PaymentGatewayPort::class,
            CertificateWorkflow::class,
            CatalogService::class,
            CourseAdminService::class,
            QuizAdminService::class,
            EnrollmentService::class,
            ProgressService::class,
            QuizService::class,
            CertificateService::class,
            ReportService::class,
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function registerLang(string $moduleRoot): void
    {
        if (!$this->app->bound('lang')) {
            return;
        }
        $translator = $this->app->make('lang');
        if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
            $translator->addNamespace('lms', $moduleRoot . '/lang');
        }
    }

    private function registerRoutes(string $moduleRoot): void
    {
        foreach (['web', 'admin'] as $channel) {
            $file = $moduleRoot . '/routes/' . $channel . '.php';
            if (\is_file($file)) {
                require $file;
            }
        }
    }

    /** سیم‌کشی رویدادها (DOC-095 §5). */
    private function registerListeners(): void
    {
        if (!$this->app->bound('events')) {
            return;
        }

        $app = $this->app;

        // جست‌وجو — ایندکس دوره‌های منتشرشده
        Event::listen('lms.course.published', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CourseSearchIndexer::class)->onCoursePublished($event, $payload);
            self::flushCatalogCache();
        });
        Event::listen('lms.course.updated', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CourseSearchIndexer::class)->onCourseUpdated($event, $payload);
            self::flushCatalogCache();
        });

        // اعلان‌ها — welcome/تکمیل/گواهی/نمره
        Event::listen('lms.student.enrolled', static function (string $event, mixed $payload) use ($app): void {
            $app->make(LmsNotifier::class)->onEnrolled($event, $payload);
        });
        Event::listen('lms.course.completed', static function (string $event, mixed $payload) use ($app): void {
            $app->make(LmsNotifier::class)->onCourseCompleted($event, $payload);
        });
        Event::listen('lms.certificate.issued', static function (string $event, mixed $payload) use ($app): void {
            $app->make(LmsNotifier::class)->onCertificateIssued($event, $payload);
        });
        Event::listen('lms.attempt.graded', static function (string $event, mixed $payload) use ($app): void {
            $app->make(LmsNotifier::class)->onAttemptGraded($event, $payload);
        });
    }

    private static function flushCatalogCache(): void
    {
        try {
            Cache::tags(['lms'])->flush();
        } catch (\Throwable) {
            // درایور بدون tag
        }
    }
}
