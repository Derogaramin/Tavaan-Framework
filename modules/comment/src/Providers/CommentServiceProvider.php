<?php

declare(strict_types=1);

namespace Modules\Comment\Providers;

use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentServiceProvider — ثبت سرویس‌های دامنه + namespace ترجمه.
 *
 * 🔒 مرز ماژول (ARCH-003): بدون ارجاع به موتور. پورت CommentStoreInterface
 * توسط ماژول مصرف‌کننده (blog) bind می‌شود.
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
    }

    public function boot(): void
    {
        if ($this->app->bound('lang')) {
            $translator = $this->app->make('lang');
            if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
                $translator->addNamespace('comment', \dirname(__DIR__, 2) . '/lang');
            }
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [CommentModerator::class, CommentThreadBuilder::class];
    }
}
