<?php

declare(strict_types=1);

namespace Modules\Content\Providers;

use Modules\Content\Application\UseCases\PageReadService;
use Modules\Content\Application\UseCases\PageWriteService;
use Modules\Content\Domain\Repositories\PageRepositoryInterface;
use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\FeedBuilder;
use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\Services\SitemapBuilder;
use Modules\Content\Infrastructure\Persistence\DatabasePageRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول محتوا
 * ContentServiceProvider — ثبت سرویس‌های دامنهٔ خالص + لایهٔ صفحات CMS
 * (مخزن/UseCaseها/routes/ویجت داشبورد) + namespace ترجمه.
 *
 * 🔒 مرز ماژول (ARCH-003): فقط Core Contracts/Facades — بدون ارجاع به موتور.
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Markdown::class, static function (ContainerInterface $c): Markdown {
            $allowImages = $c->bound('config')
                ? (bool) $c->make('config')->get('content.markdown.allow_images', true)
                : true;

            return new Markdown(allowImages: $allowImages);
        });

        $this->app->singleton(FeedBuilder::class, static fn (): FeedBuilder => new FeedBuilder());
        $this->app->singleton(SitemapBuilder::class, static fn (): SitemapBuilder => new SitemapBuilder());

        $this->app->singleton(ExcerptGenerator::class, static function (ContainerInterface $c): ExcerptGenerator {
            $chars = $c->bound('config')
                ? (int) $c->make('config')->get('content.excerpt.chars', 200)
                : 200;

            return new ExcerptGenerator(defaultChars: $chars);
        });

        $this->app->alias(Markdown::class, 'content.markdown');
        $this->app->alias(FeedBuilder::class, 'content.feed');
        $this->app->alias(SitemapBuilder::class, 'content.sitemap');
        $this->app->alias(ExcerptGenerator::class, 'content.excerpt');

        // ── صفحات CMS (content_pages) ─────────────────────────────────────
        $this->app->singleton(DatabasePageRepository::class, static fn (): DatabasePageRepository => new DatabasePageRepository());
        $this->app->alias(DatabasePageRepository::class, 'content.pages');
        if (!$this->app->bound(PageRepositoryInterface::class)) {
            $this->app->singleton(
                PageRepositoryInterface::class,
                static fn (ContainerInterface $c): PageRepositoryInterface => $c->make(DatabasePageRepository::class),
            );
        }

        $this->app->singleton(PageWriteService::class, static function (ContainerInterface $c): PageWriteService {
            $config = $c->bound('config') ? (array) $c->make('config')->get('content.pages', []) : [];

            return new PageWriteService(
                pages: $c->make(PageRepositoryInterface::class),
                markdown: $c->make(Markdown::class),
                excerpts: $c->make(ExcerptGenerator::class),
                config: $config,
            );
        });

        $this->app->singleton(PageReadService::class, static function (ContainerInterface $c): PageReadService {
            $config = $c->bound('config') ? (array) $c->make('config')->get('content.pages', []) : [];

            return new PageReadService(
                pages: $c->make(PageRepositoryInterface::class),
                sitemap: $c->make(SitemapBuilder::class),
                config: $config,
            );
        });
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        // namespace ترجمه: content::status.* / content::messages.* → modules/content/lang/<locale>/*.php
        if ($this->app->bound('lang')) {
            $translator = $this->app->make('lang');
            if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
                $translator->addNamespace('content', $moduleRoot . '/lang');
            }
        }

        if (!$this->pagesEnabled()) {
            return;
        }

        foreach (['web', 'admin'] as $channel) {
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
            Markdown::class,
            FeedBuilder::class,
            SitemapBuilder::class,
            ExcerptGenerator::class,
            PageRepositoryInterface::class,
            DatabasePageRepository::class,
            PageWriteService::class,
            PageReadService::class,
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function pagesEnabled(): bool
    {
        if (!$this->app->bound('config')) {
            return true;
        }

        return (bool) $this->app->make('config')->get('content.pages.enabled', true);
    }

    /** ویجت «صفحات» روی پیشخوان مدیریت (DOC-078 §5). */
    private function registerDashboardWidget(): void
    {
        if (!$this->app->bound('admin')) {
            return;
        }

        try {
            $admin = $this->app->make('admin');
            if (\is_object($admin) && \method_exists($admin, 'widget')) {
                $admin->widget(\Modules\Content\Dashboard\PagesWidget::class);
            }
        } catch (\Throwable) {
            // نبود موتور admin نباید بوت ماژول را بشکند
        }
    }
}
