<?php

declare(strict_types=1);

namespace Modules\News\Providers;

use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\FeedBuilder;
use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\Services\SitemapBuilder;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Media\Contracts\MediaGatewayInterface;
use Modules\News\Application\UseCases\ArticleReadService;
use Modules\News\Application\UseCases\ArticleWriteService;
use Modules\News\Application\UseCases\CommentService;
use Modules\News\Application\UseCases\FeedService;
use Modules\News\Application\UseCases\SitemapService;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Modules\News\Domain\Repositories\NewsCategoryRepositoryInterface;
use Modules\News\Domain\Repositories\NewsTagRepositoryInterface;
use Modules\News\Domain\Services\JsonLdBuilder;
use Modules\News\Infrastructure\Notifications\WorkflowNotifier;
use Modules\News\Infrastructure\Persistence\DatabaseArticleRepository;
use Modules\News\Infrastructure\Persistence\DatabaseNewsCategoryRepository;
use Modules\News\Infrastructure\Persistence\DatabaseNewsCommentStore;
use Modules\News\Infrastructure\Persistence\DatabaseNewsTagRepository;
use Modules\News\Infrastructure\Realtime\BreakingBroadcaster;
use Modules\News\Infrastructure\Search\ArticleSearchIndexer;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * NewsServiceProvider — bindingها + lang/routes + listenerها + schedule.
 *
 * 🔒 ARCH-003: فقط Core (Contracts/Facades/Support/Exceptions) + Contracts
 * ماژول‌های هم‌خانواده + SDK مجاز. Policy از راه config اعلامی (rbac.policies).
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class NewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Infrastructure: مخزن‌ها ──────────────────────────────────────
        $this->app->singleton(ArticleRepositoryInterface::class, static fn (): ArticleRepositoryInterface => new DatabaseArticleRepository());
        $this->app->singleton(NewsCategoryRepositoryInterface::class, static fn (): NewsCategoryRepositoryInterface => new DatabaseNewsCategoryRepository());
        $this->app->singleton(NewsTagRepositoryInterface::class, static fn (): NewsTagRepositoryInterface => new DatabaseNewsTagRepository());
        // 🔒 CommentStoreInterface را global bind نمی‌کنیم — هر ماژول
        // مصرف‌کننده store اختصاصی خودش را تزریق می‌کند (blog و news هم‌زیست).
        $this->app->singleton(DatabaseNewsCommentStore::class, static fn (): DatabaseNewsCommentStore => new DatabaseNewsCommentStore());

        // ── Application: UseCaseها ───────────────────────────────────────
        $this->app->singleton(ArticleWriteService::class, static function (ContainerInterface $c): ArticleWriteService {
            return new ArticleWriteService(
                articles: $c->make(ArticleRepositoryInterface::class),
                categories: $c->make(NewsCategoryRepositoryInterface::class),
                tags: $c->make(NewsTagRepositoryInterface::class),
                markdown: $c->make(Markdown::class),
                excerpts: $c->make(ExcerptGenerator::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('news', []) : [],
            );
        });

        $this->app->singleton(ArticleReadService::class, static function (ContainerInterface $c): ArticleReadService {
            return new ArticleReadService(
                articles: $c->make(ArticleRepositoryInterface::class),
                tags: $c->make(NewsTagRepositoryInterface::class),
                comments: $c->make(DatabaseNewsCommentStore::class),
                threadBuilder: $c->make(CommentThreadBuilder::class),
                users: $c->make(UserReaderInterface::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('news', []) : [],
            );
        });

        $this->app->singleton(CommentService::class, static function (ContainerInterface $c): CommentService {
            return new CommentService(
                comments: $c->make(DatabaseNewsCommentStore::class),
                moderator: $c->make(CommentModerator::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('news.comments', []) : [],
            );
        });

        $this->app->singleton(FeedService::class, static function (ContainerInterface $c): FeedService {
            return new FeedService(
                articles: $c->make(ArticleRepositoryInterface::class),
                feedBuilder: $c->make(FeedBuilder::class),
                excerpts: $c->make(ExcerptGenerator::class),
                users: $c->make(UserReaderInterface::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('news.feed', []) : [],
            );
        });

        $this->app->singleton(SitemapService::class, static fn (ContainerInterface $c): SitemapService => new SitemapService(
            articles: $c->make(ArticleRepositoryInterface::class),
            categories: $c->make(NewsCategoryRepositoryInterface::class),
            sitemapBuilder: $c->make(SitemapBuilder::class),
        ));

        $this->app->singleton(JsonLdBuilder::class, static fn (): JsonLdBuilder => new JsonLdBuilder());

        // ── Infrastructure: شنونده‌ها ────────────────────────────────────
        $this->app->singleton(ArticleSearchIndexer::class, static function (ContainerInterface $c): ArticleSearchIndexer {
            return new ArticleSearchIndexer(
                articles: $c->make(ArticleRepositoryInterface::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('news.search', []) : [],
            );
        });

        $this->app->singleton(WorkflowNotifier::class, static function (ContainerInterface $c): WorkflowNotifier {
            return new WorkflowNotifier(
                articles: $c->make(ArticleRepositoryInterface::class),
                users: $c->make(UserReaderInterface::class),
                container: $c,
                config: $c->bound('config') ? (array) $c->make('config')->get('news.notifications', []) : [],
            );
        });

        $this->app->singleton(BreakingBroadcaster::class, static function (ContainerInterface $c): BreakingBroadcaster {
            return new BreakingBroadcaster(
                config: $c->bound('config') ? (array) $c->make('config')->get('news.realtime', []) : [],
            );
        });
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        // ویوها: namespace `news::` توسط ViewServiceProvider از manifest ثبت
        // می‌شود؛ override پوسته از راه `resources/views/vendor/news`
        // (کپی‌شده توسط `tvn kit:install news`).
        $this->registerLang($moduleRoot);
        $this->registerRoutes($moduleRoot);
        $this->registerListeners();
        $this->registerSchedule();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            ArticleRepositoryInterface::class,
            NewsCategoryRepositoryInterface::class,
            NewsTagRepositoryInterface::class,
            ArticleWriteService::class,
            ArticleReadService::class,
            CommentService::class,
            FeedService::class,
            SitemapService::class,
            JsonLdBuilder::class,
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
            $translator->addNamespace('news', $moduleRoot . '/lang');
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

    /** سیم‌کشی رویدادها (DOC-092 §3/§5/§6) — قرارداد ($eventName, $payload). */
    private function registerListeners(): void
    {
        if (!$this->app->bound('events')) {
            return;
        }

        $app = $this->app;

        Event::listen('news.article.published', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ArticleSearchIndexer::class)->onPublished($event, $payload);
            $app->make(WorkflowNotifier::class)->onPublished($event, $payload);
        });
        Event::listen('news.article.updated', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ArticleSearchIndexer::class)->onUpdated($event, $payload);
        });
        Event::listen('news.article.deleted', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ArticleSearchIndexer::class)->onDeleted($event, $payload);
        });
        Event::listen('news.article.approved', static function (string $event, mixed $payload) use ($app): void {
            $app->make(WorkflowNotifier::class)->onApproved($event, $payload);
        });
        Event::listen('news.article.rejected', static function (string $event, mixed $payload) use ($app): void {
            $app->make(WorkflowNotifier::class)->onRejected($event, $payload);
        });
        Event::listen('news.breaking.published', static function (string $event, mixed $payload) use ($app): void {
            $app->make(BreakingBroadcaster::class)->onBreaking($event, $payload);
        });
    }

    /**
     * زمان‌بندی انتشار خودکار (DOC-092 §3) — هر دقیقه با schedule:run.
     *
     * 🔒 در محیط testing ثبت نمی‌شود: تست‌ها باید قطعی بمانند (تست‌های موتور
     * schedule رویدادهای ثبت‌شده را می‌شمارند)؛ E2E خودِ ماژول دستور را
     * مستقیم صدا می‌زند. در local/production روی `tvn schedule:run` (cron)
     * فعال است.
     */
    private function registerSchedule(): void
    {
        if (!$this->app->bound('schedule')) {
            return;
        }
        if ($this->runningInTests()) {
            return;
        }

        $schedule = $this->app->make('schedule');
        if (\is_object($schedule) && \method_exists($schedule, 'command')) {
            $event = $schedule->command('news:publish-scheduled');
            if (\is_object($event) && \method_exists($event, 'everyMinute')) {
                $event->everyMinute();
            }
        }
    }

    /**
     * آیا در حالت تست هستیم؟ — `$this->app` در Providerها Container است
     * (نه Application)؛ از helper سراسری `app()` و $_SERVER استفاده می‌شود.
     */
    private function runningInTests(): bool
    {
        if ((string) ($_SERVER['TVN_APP_ENV'] ?? '') === 'testing') {
            return true;
        }

        if (\function_exists('app')) {
            $application = app();
            if ($application instanceof \Tavaan\Core\Application\Application) {
                return $application->runningUnitTests();
            }
        }

        return false;
    }
}
