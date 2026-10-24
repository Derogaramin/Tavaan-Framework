<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade روتر.
 *
 * @method static \Tavaan\Engine\Http\Routing\Route get(string $uri, mixed $action)
 * @method static \Tavaan\Engine\Http\Routing\Route post(string $uri, mixed $action)
 * @method static \Tavaan\Engine\Http\Routing\Route put(string $uri, mixed $action)
 * @method static \Tavaan\Engine\Http\Routing\Route patch(string $uri, mixed $action)
 * @method static \Tavaan\Engine\Http\Routing\Route delete(string $uri, mixed $action)
 * @method static \Tavaan\Engine\Http\Routing\Route match(array $methods, string $uri, mixed $action)
 * @method static \Tavaan\Engine\Http\Routing\Route any(string $uri, mixed $action)
 * @method static \Tavaan\Engine\Http\Routing\Route view(string $uri, string $view, array $data = [], int $status = 200)
 * @method static \Tavaan\Engine\Http\Routing\Route redirect(string $uri, string $to, int $status = 302)
 * @method static \Tavaan\Engine\Http\Routing\Route fallback(mixed $action)
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
