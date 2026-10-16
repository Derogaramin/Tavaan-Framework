<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Http;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Observability\Metrics\MetricsRegistry;

/**
 * تَوان | Tavaan — Observability
 * MetricsMiddleware — ثبت خودکار سنجه‌های HTTP هر درخواست.
 *
 * - `http_requests_total{route,method,status}` (Counter)
 * - `http_request_duration_seconds{route,method}` (Histogram)
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class MetricsMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $start = \hrtime(true);

        /** @var ResponseInterface $response */
        $response = $next($request);

        try {
            if ($this->container->bound('metrics')) {
                /** @var MetricsRegistry $metrics */
                $metrics = $this->container->make('metrics');
                $route = $this->routeLabel();
                $method = \method_exists($request, 'method') ? (string) $request->method() : 'GET';
                $status = (string) $response->statusCode();
                $seconds = (\hrtime(true) - $start) / 1e9;

                $metrics->counter('http_requests_total', 'Total HTTP requests')
                    ->increment(1, ['route' => $route, 'method' => $method, 'status' => $status]);
                $metrics->histogram('http_request_duration_seconds', null, 'HTTP request duration in seconds')
                    ->observe($seconds, ['route' => $route, 'method' => $method]);
            }
        } catch (\Throwable) {
            // 🔒 رصدپذیری هرگز نباید درخواست را بیندازد
        }

        return $response;
    }

    /**
     * برچسب route با cardinality محدود: نام route یا الگوی URI
     * (هرگز مسیر واقعی با id — از انفجار series جلوگیری می‌کند).
     */
    private function routeLabel(): string
    {
        try {
            if (!$this->container->bound('router')) {
                return 'unmatched';
            }
            $router = $this->container->make('router');
            $match = \method_exists($router, 'current') ? $router->current() : null;
            if ($match === null || !isset($match->route)) {
                return 'unmatched';
            }
            $name = \method_exists($match->route, 'getName') ? (string) ($match->route->getName() ?? '') : '';
            if ($name !== '') {
                return $name;
            }

            return \method_exists($match->route, 'uri') ? (string) $match->route->uri() : 'unmatched';
        } catch (\Throwable) {
            return 'unmatched';
        }
    }
}
