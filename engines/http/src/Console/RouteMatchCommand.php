<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Http\Routing\Router;

/**
 * دیباگ تطبیق روت (`tvn route:match GET /shop/products/1`).
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class RouteMatchCommand extends Command
{
    protected string $signature = 'route:match {method : متد HTTP} {path : مسیر}';

    protected string $description = 'نمایش روت مطابقی برای یک درخواست (دیباگ)';

    public function handle(): int
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        try {
            $match = $router->matchUri((string) $this->argument('method', 'GET'), (string) $this->argument('path', '/'));
        } catch (\Tavaan\Core\Exceptions\Http\RouteNotFoundException $e) {
            $this->error('  404 — روتی مطابقت نکرد.');

            return self::FAILURE;
        } catch (\Tavaan\Core\Exceptions\Http\MethodNotAllowedException $e) {
            $this->error('  405 — متد مجاز نیست. Allow: ' . \implode(', ', (array) ($e->headers['Allow'] ?? [])));

            return self::FAILURE;
        }

        $route = $match->route;
        $this->info('  ✓ matched');
        $this->table(['کلید', 'مقدار'], [
            ['URI', $route->uri()],
            ['Methods', \implode('|', $route->methods())],
            ['Name', $route->getName() !== '' ? $route->getName() : '—'],
            ['Parameters', \json_encode($match->allParameters(), JSON_UNESCAPED_UNICODE) ?: '—'],
            ['Middleware', \implode(',', $route->getMiddleware()) ?: '—'],
            ['Specificity', (string) $route->specificity()],
        ]);

        return self::SUCCESS;
    }
}
