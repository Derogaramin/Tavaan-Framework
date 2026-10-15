<?php

declare(strict_types=1);

namespace Tavaan\Engine\View;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — View
 * ViewServiceProvider — ثبت ViewFactory به‌عنوان `view`.
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ViewFactory::class, static function (ContainerInterface $c): ViewFactory {
            $config = $c->bound('config') ? (array) $c->make('config')->get('view', []) : [];

            return new ViewFactory($c, $config);
        });
        $this->app->alias(ViewFactory::class, 'view');
    }

    public function boot(): void
    {
        if (!$this->app->bound('modules') || !$this->app->bound('view')) {
            return;
        }

        // namespace ویوهای ماژول/موتور: shop:: → modules/shop/views
        $registry = $this->app->make('modules');
        /** @var ViewFactory $view */
        $view = $this->app->make('view');

        foreach ($registry->all() as $module) {
            $viewsDir = $module->path() . '/views';
            if (\is_dir($viewsDir)) {
                $short = \substr($module->id(), (int) \strrpos($module->id(), '.') + 1);
                $view->addNamespace($short, $viewsDir);
            }
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [ViewFactory::class, 'view'];
    }
}
