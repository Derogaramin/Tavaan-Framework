<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Error\ErrorHandler;
use Tavaan\Engine\Error\ExceptionHandler;

/**
 * تَوان | Tavaan — Core
 * HandleExceptions — B4: ثبت ErrorHandler کامل + replay خطاهای زودهنگام.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class HandleExceptions implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        /** @var ExceptionHandler $handler */
        $handler = $app->make(ExceptionHandler::class);

        $failOnDeprecation = $app->bound('config')
            && (bool) $app->make('config')->get('error.deprecations.fail_in_ci', false);

        ErrorHandler::register(
            $handler,
            environment: $app->environment(),
            failOnDeprecation: $failOnDeprecation,
            logger: $app->bound('log') ? $app->make('log') : null,
        );

        // Replay خطاهای پیش از بوت
        foreach (\Tavaan\Core\Error\EarlyErrorManager::drain() as $early) {
            $handler->report($early);
        }
    }
}
