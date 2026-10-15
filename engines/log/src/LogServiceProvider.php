<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Log
 * LogServiceProvider — ثبت LogManager به‌عنوان `log` + LoggerInterface.
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LogManager::class, static function (ContainerInterface $c): LogManager {
            $config = $c->bound('config') ? $c->make('config') : null;

            return new LogManager($config);
        });
        $this->app->alias(LogManager::class, 'log');
        $this->app->singleton(LoggerInterface::class, static fn (ContainerInterface $c): LoggerInterface => $c->make('log'));
    }

    public function shutdown(): void
    {
        if ($this->app->resolved('log')) {
            $this->app->make('log')->flush();
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return ['log', LogManager::class, LoggerInterface::class];
    }
}
