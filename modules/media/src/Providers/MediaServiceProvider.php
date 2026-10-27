<?php

declare(strict_types=1);

namespace Modules\Media\Providers;

use Modules\Media\Contracts\MediaGatewayInterface;
use Modules\Media\Infrastructure\FacadeMediaGateway;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول رسانه
 * MediaServiceProvider — bind پورت MediaGatewayInterface + routes + lang + ویجت.
 *
 * @api 1.0
 * @see modules/media/README.md
 */
final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaGatewayInterface::class, static fn (): MediaGatewayInterface => new FacadeMediaGateway());
        $this->app->alias(MediaGatewayInterface::class, 'media.gateway');
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        if ($this->app->bound('lang')) {
            $translator = $this->app->make('lang');
            if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
                $translator->addNamespace('media', $moduleRoot . '/lang');
            }
        }

        // روت‌های پنل (کتابخانهٔ رسانه)
        $adminRoutes = $moduleRoot . '/routes/admin.php';
        if (\is_file($adminRoutes)) {
            require $adminRoutes;
        }

        $this->registerDashboardWidget();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [MediaGatewayInterface::class, 'media.gateway'];
    }

    /** ویجت «رسانه» روی پیشخوان مدیریت (DOC-078 §5). */
    private function registerDashboardWidget(): void
    {
        if (!$this->app->bound('admin')) {
            return;
        }

        try {
            $admin = $this->app->make('admin');
            if (\is_object($admin) && \method_exists($admin, 'widget')) {
                $admin->widget(\Modules\Media\Dashboard\MediaWidget::class);
            }
        } catch (\Throwable) {
            // نبود موتور admin نباید بوت ماژول را بشکند
        }
    }
}
