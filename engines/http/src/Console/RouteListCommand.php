<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Http\Routing\Router;

/**
 * فهرست روت‌ها (AC-RT-08: `tvn route:list`).
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class RouteListCommand extends Command
{
    protected string $signature = 'route:list {--method= : فیلتر متد} {--path= : فیلتر مسیر} {--name= : فیلتر نام} {--json}';

    protected string $description = 'فهرست روت‌های ثبت‌شده با middleware';

    public function handle(): int
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        $rows = [];
        foreach ($router->routes()->all() as $route) {
            $methods = \implode('|', $route->methods());
            $name = $route->getName();
            $middleware = \implode(',', $route->getMiddleware());
            $action = $route->action() instanceof \Closure
                ? 'Closure'
                : (\is_array($route->action())
                    ? (\is_object($route->action()[0]) ? $route->action()[0]::class : $route->action()[0]) . '@' . $route->action()[1]
                    : (string) $route->action());

            if (($m = $this->option('method')) && !\str_contains(\strtoupper($methods), \strtoupper((string) $m))) {
                continue;
            }
            if (($p = $this->option('path')) && !\str_contains($route->uri(), (string) $p)) {
                continue;
            }
            if (($n = $this->option('name')) && !\str_contains($name, (string) $n)) {
                continue;
            }

            $rows[] = [$methods, $route->uri(), $name !== '' ? $name : '—', $action, $middleware !== '' ? $middleware : '—'];
        }

        if ($this->option('json')) {
            $this->line((string) \json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->comment('  هیچ روتی ثبت نشده است.');

            return self::SUCCESS;
        }

        $this->table(['Method', 'URI', 'Name', 'Action', 'Middleware'], $rows);
        $this->comment('  ' . \count($rows) . ' route(s)');

        return self::SUCCESS;
    }
}
