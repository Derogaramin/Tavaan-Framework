<?php

declare(strict_types=1);

namespace Modules\Content\Providers;

use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\FeedBuilder;
use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\Services\SitemapBuilder;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول محتوا
 * ContentServiceProvider — ثبت سرویس‌های دامنهٔ خالص + namespace ترجمه.
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
    }

    public function boot(): void
    {
        // namespace ترجمه: content::status.* → modules/content/lang/<locale>/status.php
        if ($this->app->bound('lang')) {
            $translator = $this->app->make('lang');
            if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
                $translator->addNamespace('content', \dirname(__DIR__, 2) . '/lang');
            }
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Markdown::class, FeedBuilder::class, SitemapBuilder::class, ExcerptGenerator::class];
    }
}
