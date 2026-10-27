<?php

declare(strict_types=1);

namespace Modules\Faq\Providers;

use Modules\Content\Domain\Services\Markdown;
use Modules\Faq\Application\UseCases\FaqReadService;
use Modules\Faq\Application\UseCases\FaqWriteService;
use Modules\Faq\Domain\Repositories\FaqCategoryRepositoryInterface;
use Modules\Faq\Domain\Repositories\FaqItemRepositoryInterface;
use Modules\Faq\Infrastructure\Persistence\DatabaseFaqCategoryRepository;
use Modules\Faq\Infrastructure\Persistence\DatabaseFaqItemRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqServiceProvider — مخزن‌ها + UseCaseها + routes + ویجت داشبورد + lang.
 *
 * 🔒 مرز ماژول (ARCH-003): فقط Core Contracts/Facades — بدون ارجاع به موتور.
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class FaqServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseFaqCategoryRepository::class, static fn (): DatabaseFaqCategoryRepository => new DatabaseFaqCategoryRepository());
        $this->app->singleton(DatabaseFaqItemRepository::class, static fn (): DatabaseFaqItemRepository => new DatabaseFaqItemRepository());
        $this->app->alias(DatabaseFaqCategoryRepository::class, 'faq.categories');
        $this->app->alias(DatabaseFaqItemRepository::class, 'faq.items');

        if (!$this->app->bound(FaqCategoryRepositoryInterface::class)) {
            $this->app->singleton(
                FaqCategoryRepositoryInterface::class,
                static fn (ContainerInterface $c): FaqCategoryRepositoryInterface => $c->make(DatabaseFaqCategoryRepository::class),
            );
        }
        if (!$this->app->bound(FaqItemRepositoryInterface::class)) {
            $this->app->singleton(
                FaqItemRepositoryInterface::class,
                static fn (ContainerInterface $c): FaqItemRepositoryInterface => $c->make(DatabaseFaqItemRepository::class),
            );
        }

        $this->app->singleton(FaqWriteService::class, static function (ContainerInterface $c): FaqWriteService {
            $config = $c->bound('config') ? (array) $c->make('config')->get('faq', []) : [];

            // Markdown از ماژول content (وابستگی اعلام‌شده در module.json)
            $markdown = $c->bound(Markdown::class)
                ? $c->make(Markdown::class)
                : new Markdown(allowImages: false);

            return new FaqWriteService(
                categories: $c->make(FaqCategoryRepositoryInterface::class),
                items: $c->make(FaqItemRepositoryInterface::class),
                markdown: $markdown,
                config: $config,
            );
        });

        $this->app->singleton(FaqReadService::class, static function (ContainerInterface $c): FaqReadService {
            $config = $c->bound('config') ? (array) $c->make('config')->get('faq', []) : [];

            return new FaqReadService(
                categories: $c->make(FaqCategoryRepositoryInterface::class),
                items: $c->make(FaqItemRepositoryInterface::class),
                config: $config,
            );
        });
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        // namespace ترجمه: faq::messages.* / faq::status.* → modules/faq/lang/<locale>/*.php
        if ($this->app->bound('lang')) {
            $translator = $this->app->make('lang');
            if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
                $translator->addNamespace('faq', $moduleRoot . '/lang');
            }
        }

        if (!$this->faqEnabled()) {
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
            FaqCategoryRepositoryInterface::class,
            FaqItemRepositoryInterface::class,
            DatabaseFaqCategoryRepository::class,
            DatabaseFaqItemRepository::class,
            FaqWriteService::class,
            FaqReadService::class,
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function faqEnabled(): bool
    {
        if (!$this->app->bound('config')) {
            return true;
        }

        return (bool) $this->app->make('config')->get('faq.enabled', true);
    }

    /** ویجت «پرسش‌های متداول» روی پیشخوان مدیریت (DOC-078 §5). */
    private function registerDashboardWidget(): void
    {
        if (!$this->app->bound('admin')) {
            return;
        }

        try {
            $admin = $this->app->make('admin');
            if (\is_object($admin) && \method_exists($admin, 'widget')) {
                $admin->widget(\Modules\Faq\Dashboard\FaqWidget::class);
            }
        } catch (\Throwable) {
            // نبود موتور admin نباید بوت ماژول را بشکند
        }
    }
}
