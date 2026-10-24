<?php

declare(strict_types=1);

namespace Modules\Blog\Providers;

use Modules\Blog\Application\UseCases\CommentService;
use Modules\Blog\Application\UseCases\FeedService;
use Modules\Blog\Application\UseCases\PostReadService;
use Modules\Blog\Application\UseCases\PostWriteService;
use Modules\Blog\Application\UseCases\SitemapService;
use Modules\Blog\Domain\Repositories\CategoryRepositoryInterface;
use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Modules\Blog\Domain\Repositories\TagRepositoryInterface;
use Modules\Blog\Infrastructure\Notifications\CommentNotifier;
use Modules\Blog\Infrastructure\Persistence\DatabaseCategoryRepository;
use Modules\Blog\Infrastructure\Persistence\DatabaseCommentStore;
use Modules\Blog\Infrastructure\Persistence\DatabasePostRepository;
use Modules\Blog\Infrastructure\Persistence\DatabaseTagRepository;
use Modules\Blog\Infrastructure\Search\PostSearchIndexer;
use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\FeedBuilder;
use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\Services\SitemapBuilder;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * BlogServiceProvider — bindingها + view/lang/routes + Policy + Listenerها.
 *
 * 🔒 ARCH-003: فقط Core (Contracts/Facades/Support) + Contracts ماژول‌های
 * هم‌خانواده (content/comment/media/core-user). بدون ارجاع به موتور.
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Infrastructure: مخزن‌ها ──────────────────────────────────────
        $this->app->singleton(PostRepositoryInterface::class, static fn (): PostRepositoryInterface => new DatabasePostRepository());
        $this->app->singleton(CategoryRepositoryInterface::class, static fn (): CategoryRepositoryInterface => new DatabaseCategoryRepository());
        $this->app->singleton(TagRepositoryInterface::class, static fn (): TagRepositoryInterface => new DatabaseTagRepository());
        $this->app->singleton(DatabaseCommentStore::class, static fn (): DatabaseCommentStore => new DatabaseCommentStore());
        $this->app->singleton(CommentStoreInterface::class, static fn (ContainerInterface $c): CommentStoreInterface => $c->make(DatabaseCommentStore::class));

        // ── Application: سرویس‌های UseCase ───────────────────────────────
        $this->app->singleton(PostWriteService::class, static function (ContainerInterface $c): PostWriteService {
            return new PostWriteService(
                posts: $c->make(PostRepositoryInterface::class),
                categories: $c->make(CategoryRepositoryInterface::class),
                tags: $c->make(TagRepositoryInterface::class),
                markdown: $c->make(Markdown::class),
                excerpts: $c->make(ExcerptGenerator::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('blog', []) : [],
            );
        });

        $this->app->singleton(PostReadService::class, static function (ContainerInterface $c): PostReadService {
            return new PostReadService(
                posts: $c->make(PostRepositoryInterface::class),
                tags: $c->make(TagRepositoryInterface::class),
                comments: $c->make(CommentStoreInterface::class),
                threadBuilder: $c->make(CommentThreadBuilder::class),
                users: $c->make(UserReaderInterface::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('blog', []) : [],
            );
        });

        $this->app->singleton(CommentService::class, static function (ContainerInterface $c): CommentService {
            return new CommentService(
                comments: $c->make(CommentStoreInterface::class),
                moderator: $c->make(CommentModerator::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('blog.comments', []) : [],
            );
        });

        $this->app->singleton(FeedService::class, static function (ContainerInterface $c): FeedService {
            return new FeedService(
                posts: $c->make(PostRepositoryInterface::class),
                feedBuilder: $c->make(FeedBuilder::class),
                excerpts: $c->make(ExcerptGenerator::class),
                users: $c->make(UserReaderInterface::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('blog.feed', []) : [],
            );
        });

        $this->app->singleton(SitemapService::class, static fn (ContainerInterface $c): SitemapService => new SitemapService(
            posts: $c->make(PostRepositoryInterface::class),
            categories: $c->make(CategoryRepositoryInterface::class),
            sitemapBuilder: $c->make(SitemapBuilder::class),
        ));

        // ── Infrastructure: شنونده‌ها ────────────────────────────────────
        $this->app->singleton(PostSearchIndexer::class, static function (ContainerInterface $c): PostSearchIndexer {
            return new PostSearchIndexer(
                posts: $c->make(PostRepositoryInterface::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('blog.search', []) : [],
            );
        });

        $this->app->singleton(CommentNotifier::class, static function (ContainerInterface $c): CommentNotifier {
            return new CommentNotifier(
                posts: $c->make(PostRepositoryInterface::class),
                users: $c->make(UserReaderInterface::class),
                container: $c,
                config: $c->bound('config') ? (array) $c->make('config')->get('blog.comments', []) : [],
            );
        });
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        // ویوها: namespace `blog::` توسط ViewServiceProvider از manifest
        // ثبت می‌شود؛ override پوسته از راه مکانیزم استاندارد
        // `resources/views/vendor/blog` (کپی‌شده توسط `tvn kit:install`).
        $this->registerLang($moduleRoot);
        $this->registerRoutes($moduleRoot);
        $this->registerListeners();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            PostRepositoryInterface::class,
            CategoryRepositoryInterface::class,
            TagRepositoryInterface::class,
            CommentStoreInterface::class,
            PostWriteService::class,
            PostReadService::class,
            CommentService::class,
            FeedService::class,
            SitemapService::class,
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
            $translator->addNamespace('blog', $moduleRoot . '/lang');
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

    // Policy از راه config اعلامی ثبت می‌شود (modules/blog/config/rbac.php →
    // rbac.policies) — بدون resolve زودهنگام Gate در boot (M9).

    /** سیم‌کشی رویدادها (DOC-091 §5) — listener با قرارداد ($eventName, $payload). */
    private function registerListeners(): void
    {
        if (!$this->app->bound('events')) {
            return;
        }

        $app = $this->app;

        Event::listen('blog.post.published', static function (string $event, mixed $payload) use ($app): void {
            $app->make(PostSearchIndexer::class)->onPublished($event, $payload);
        });
        Event::listen('blog.post.updated', static function (string $event, mixed $payload) use ($app): void {
            $app->make(PostSearchIndexer::class)->onUpdated($event, $payload);
        });
        Event::listen('blog.post.deleted', static function (string $event, mixed $payload) use ($app): void {
            $app->make(PostSearchIndexer::class)->onDeleted($event, $payload);
        });

        Event::listen('blog.comment.submitted', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CommentNotifier::class)->onSubmitted($event, $payload);
        });
        Event::listen('blog.comment.approved', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CommentNotifier::class)->onApproved($event, $payload);
        });
    }
}
