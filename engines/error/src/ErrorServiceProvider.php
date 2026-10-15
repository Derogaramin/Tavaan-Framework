<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Error
 * ErrorServiceProvider — ثبت ExceptionHandler (ثبت ErrorHandler در Bootstrapper).
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class ErrorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExceptionHandler::class, static function (ContainerInterface $c): ExceptionHandler {
            return new ExceptionHandler(
                logger: $c->bound('log') ? $c->make('log') : null,
                config: $c->bound('config') ? $c->make('config') : null,
                events: $c->bound('events') ? $c->make('events') : null,
            );
        });
        $this->app->alias(ExceptionHandler::class, 'error.handler');
        $this->app->singleton(ExceptionMapper::class, static fn (ContainerInterface $c): ExceptionMapper => $c->make(ExceptionHandler::class)->mapper());
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [ExceptionHandler::class, ExceptionMapper::class, 'error.handler'];
    }
}
