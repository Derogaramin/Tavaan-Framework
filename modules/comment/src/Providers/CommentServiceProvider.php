<?php

declare(strict_types=1);

namespace Modules\Comment\Providers;

use Modules\Comment\Application\CommentableRegistry;
use Modules\Comment\Application\UseCases\ModerationService;
use Modules\Comment\Application\UseCases\SubmitCommentService;
use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\Comment\Infrastructure\Persistence\DatabaseCommentStore;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentServiceProvider — سرویس‌های دامنه + حالت standalone (store/registry/
 * UseCaseها/routes/ویجت داشبورد) + namespace ترجمه.
 *
 * 🔒 مرز ماژول (ARCH-003): بدون ارجاع به موتور. binding سراسری
 * CommentStoreInterface فقط در حالت standalone ثبت می‌شود — ماژول‌های دارای
 * store اختصاصی (blog/news) binding خودشان را در provider خود تزریق می‌کنند
 * و این ماژول هرگز آن‌ها را override نمی‌کند (bind تنها در صورت نبودِ binding).
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class CommentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommentModerator::class, static function (ContainerInterface $c): CommentModerator {
            $config = $c->bound('config')
                ? (array) $c->make('config')->get('comment.moderation', [])
                : [];

            return new CommentModerator($config);
        });

        $this->app->singleton(CommentThreadBuilder::class, static function (ContainerInterface $c): CommentThreadBuilder {
            $maxDepth = $c->bound('config')
                ? (int) $c->make('config')->get('comment.thread.max_depth', 8)
                : 8;

            return new CommentThreadBuilder(maxDepth: $maxDepth);
        });

        $this->app->alias(CommentModerator::class, 'comment.moderator');
        $this->app->alias(CommentThreadBuilder::class, 'comment.thread');

        // ── حالت standalone ────────────────────────────────────────────────
        $this->app->singleton(DatabaseCommentStore::class, static fn (): DatabaseCommentStore => new DatabaseCommentStore());

        $this->app->singleton(CommentableRegistry::class, static function (ContainerInterface $c): CommentableRegistry {
            $types = $c->bound('config')
                ? (array) $c->make('config')->get('comment.types', [])
                : [];

            return new CommentableRegistry($types);
        });
        $this->app->alias(CommentableRegistry::class, 'comment.registry');

        $this->app->singleton(SubmitCommentService::class, static function (ContainerInterface $c): SubmitCommentService {
            $config = $c->bound('config') ? (array) $c->make('config')->get('comment', []) : [];

            return new SubmitCommentService(
                store: $c->make(DatabaseCommentStore::class),
                registry: $c->make(CommentableRegistry::class),
                moderator: $c->make(CommentModerator::class),
                threadBuilder: $c->make(CommentThreadBuilder::class),
                config: [
                    ...($config['standalone'] ?? []),
                    'thread' => $config['thread'] ?? [],
                ],
            );
        });

        $this->app->singleton(ModerationService::class, static function (ContainerInterface $c): ModerationService {
            return new ModerationService(store: $c->make(DatabaseCommentStore::class));
        });

        // پورت عمومی — فقط وقتی binding دیگری وجود نداشته باشد (هم‌زیستی با
        // store اختصاصی blog/news که global bind نمی‌شوند).
        if (!$this->app->bound(CommentStoreInterface::class)) {
            $this->app->singleton(CommentStoreInterface::class, static fn (ContainerInterface $c): CommentStoreInterface => $c->make(DatabaseCommentStore::class));
            $this->app->alias(CommentStoreInterface::class, 'comment.store');
        }
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        if ($this->app->bound('lang')) {
            $translator = $this->app->make('lang');
            if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
                $translator->addNamespace('comment', $moduleRoot . '/lang');
            }
        }

        if (!$this->standaloneEnabled()) {
            return;
        }

        foreach (['web', 'admin', 'api'] as $channel) {
            $file = $moduleRoot . '/routes/' . $channel . '.php';
            if (\is_file($file)) {
                require $file;
            }
        }

        $this->registerDashboardWidget();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            CommentModerator::class,
            CommentThreadBuilder::class,
            CommentableRegistry::class,
            SubmitCommentService::class,
            ModerationService::class,
            DatabaseCommentStore::class,
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function standaloneEnabled(): bool
    {
        if (!$this->app->bound('config')) {
            return true;
        }

        return (bool) $this->app->make('config')->get('comment.standalone.enabled', true);
    }

    /** ویجت «نظرات» روی پیشخوان مدیریت (DOC-078 §5). */
    private function registerDashboardWidget(): void
    {
        if (!$this->app->bound('admin')) {
            return;
        }

        try {
            $admin = $this->app->make('admin');
            if (\is_object($admin) && \method_exists($admin, 'widget')) {
                $admin->widget(\Modules\Comment\Dashboard\CommentsWidget::class);
            }
        } catch (\Throwable) {
            // نبود موتور admin نباید بوت ماژول را بشکند
        }
    }
}
