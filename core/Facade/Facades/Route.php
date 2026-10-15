<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;
use Tavaan\Engine\Http\Routing\Route as RouteObject;

/**
 * Facade روتر.
 *
 * @method static RouteObject get(string $uri, mixed $action)
 * @method static RouteObject post(string $uri, mixed $action)
 * @method static RouteObject put(string $uri, mixed $action)
 * @method static RouteObject patch(string $uri, mixed $action)
 * @method static RouteObject delete(string $uri, mixed $action)
 * @method static RouteObject match(array $methods, string $uri, mixed $action)
 * @method static RouteObject any(string $uri, mixed $action)
 * @method static RouteObject view(string $uri, string $view, array $data = [], int $status = 200)
 * @method static RouteObject redirect(string $uri, string $to, int $status = 302)
 * @method static RouteObject fallback(mixed $action)
 * @method static \Tavaan\Engine\Http\Routing\RouteRegistrar prefix(string $prefix)
 * @method static \Tavaan\Engine\Http\Routing\RouteRegistrar name(string $name)
 * @method static \Tavaan\Engine\Http\Routing\RouteRegistrar middleware(array|string $mw)
 * @method static \Tavaan\Engine\Http\Routing\RouteRegistrar domain(string $domain)
 * @method static void group(array $attributes, \Closure $routes)
 * @method static \Tavaan\Engine\Http\Routing\ResourceRegistrar resource(string $name, string $controller, array $only = [], array $except = [])
 * @method static \Tavaan\Engine\Http\Routing\ResourceRegistrar apiResource(string $name, string $controller, array $only = [], array $except = [])
 * @method static bool has(string $name)
 * @method static \Tavaan\Engine\Http\Routing\RouteCollection routes()
 * @mixin \Tavaan\Engine\Http\Routing\Router
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class Route extends Facade
{
    protected static function accessor(): string
    {
        return 'router';
    }
}
