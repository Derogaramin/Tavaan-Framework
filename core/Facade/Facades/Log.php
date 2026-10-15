<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade لاگ.
 *
 * @method static void emergency(string $message, array|\Closure $context = [])
 * @method static void alert(string $message, array|\Closure $context = [])
 * @method static void critical(string $message, array|\Closure $context = [])
 * @method static void error(string $message, array|\Closure $context = [])
 * @method static void warning(string $message, array|\Closure $context = [])
 * @method static void notice(string $message, array|\Closure $context = [])
 * @method static void info(string $message, array|\Closure $context = [])
 * @method static void debug(string $message, array|\Closure $context = [])
 * @method static \Tavaan\Engine\Log\Logger channel(string $name)
 * @mixin \Tavaan\Engine\Log\LogManager
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class Log extends Facade
{
    protected static function accessor(): string
    {
        return 'log';
    }
}
