<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Engine\Module\ModuleRegistry;
use Tavaan\Engine\View\ViewFactory;

/**
 * تَوان | Tavaan — Admin
 * AdminServiceProvider — ثبت AdminManager + namespace ویجت‌های view +
 * بارگذاری `routes/admin.php` اپ + ثبت routeهای CRUD + منوی manifestها.
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminManager::class, static function (ContainerInterface $c): AdminManager {
            $config = $c->bound('config') ? (array) ($c->make('config')->get('admin', []) ?? []) : [];

            return new AdminManager($c, $config);
        });

        $this->app->alias(AdminManager::class, 'admin');
    }

    public function boot(): void
    {
        // ۱) namespace ویوها — override خودکار در resources/views/vendor/admin (AC-ADM-04)
        if ($this->app->bound('view')) {
            $view = $this->app->make('view');
            if ($view instanceof ViewFactory) {
                $view->addNamespace('admin', \dirname(__DIR__) . '/views');
            }
        }

        /** @var AdminManager $admin */
        $admin = $this->app->make(AdminManager::class);

        // ۲) تعریف resourceهای اپ (routes/admin.php — اختیاری)
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';
        $routesFile = $base . '/routes/admin.php';
        if ($base !== '' && \is_file($routesFile)) {
            require $routesFile;
        }

        // ۳) routeهای عمومی پیشخوان/CRUD
        if ($this->app->bound(Router::class)) {
            $admin->registerRoutes($this->app->make(Router::class));
        }

        // ۴) منوی ماژول‌ها از manifest (module.json → navigation)
        if ($this->app->bound(ModuleRegistry::class)) {
            $registry = $this->app->make(ModuleRegistry::class);
            if ($registry instanceof ModuleRegistry) {
                $admin->menu()->addFromManifests($registry->all());
            }
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [AdminManager::class, 'admin'];
    }
}
