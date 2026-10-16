<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\View\ViewFactory;

/**
 * تَوان | Tavaan — Report
 * ReportServiceProvider — ثبت ReportManager به‌عنوان `report` (Facade: Report).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        ReportResolver::set($this->app);

        $this->app->singleton(ReportManager::class, static function (ContainerInterface $c): ReportManager {
            /** @var array<string, mixed> $config */
            $config = $c->bound('config') ? (array) $c->make('config')->get('report', []) : [];

            $db = null;
            if ($c->bound('db')) {
                $candidate = $c->make('db');
                $db = $candidate instanceof ConnectionManager ? $candidate : null;
            }

            $view = null;
            if ($c->bound('view')) {
                $candidate = $c->make('view');
                $view = $candidate instanceof ViewFactory ? $candidate : null;
            }

            return new ReportManager($config, $db, $view);
        });
        $this->app->alias(ReportManager::class, 'report');
    }

    public function boot(): void
    {
        // namespace قالب‌های view موتور: `report::table`
        if ($this->app->bound('view')) {
            $view = $this->app->make('view');
            if ($view instanceof ViewFactory) {
                $path = (\defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : \dirname(__DIR__, 2)) . '/engines/report/views';
                if (\is_dir($path)) {
                    $view->addNamespace('report', $path);
                }
            }
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [ReportManager::class, 'report'];
    }
}
