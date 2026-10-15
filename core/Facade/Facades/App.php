<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facade;

/**
 * Facade کانتینر/اپلیکیشن.
 *
 * @method static mixed make(string $abstract, array $parameters = [])
 * @method static void bind(string $abstract, \Closure|string|null $concrete = null, bool $shared = false)
 * @method static void singleton(string $abstract, \Closure|string|null $concrete = null)
 * @method static mixed call(callable|array|string $callback, array $parameters = [])
 * @mixin \Tavaan\Engine\Container\Container
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final class App extends Facade
{
    protected static function accessor(): string
    {
        return ContainerInterface::class;
    }
}
