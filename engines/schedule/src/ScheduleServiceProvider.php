<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schedule;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Schedule
 * ScheduleServiceProvider — ثبت ScheduleManager به‌عنوان `schedule` (Facade: Schedule).
 *
 * رویدادهای زمان‌بندی در `routes/console.php` (در صورت وجود) ثبت می‌شوند (DOC-062 §2).
 *
 * @api 1.0
 * @see docs/03-engines/schedule.md
 */
final class ScheduleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ScheduleManager::class, static function (ContainerInterface $c): ScheduleManager {
            return new ScheduleManager(
                $c,
                $c->bound('config') ? $c->make('config') : null,
                $c->bound(CacheRepository::class) ? $c->make(CacheRepository::class) : ($c->bound('cache') ? $c->make('cache') : null),
                $c->bound(LoggerInterface::class) ? $c->make(LoggerInterface::class) : null,
            );
        });
        $this->app->alias(ScheduleManager::class, 'schedule');
    }

    public function boot(): void
    {
        // ثبت رویدادهای زمان‌بندی اپلیکیشن (DOC-062 §2)
        $file = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/routes/console.php' : null;
        if ($file !== null && \is_file($file)) {
            $schedule = $this->app->make(ScheduleManager::class);
            require $file;
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [ScheduleManager::class, 'schedule'];
    }
}
