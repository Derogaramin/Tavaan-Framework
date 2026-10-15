<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Http
 * MaintenanceModeMiddleware — ۵۰۳ وقتی storage/framework/down موجود است.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class MaintenanceModeMiddleware implements MiddlewareInterface
{
    /** @param list<string> $except مسیرهای معاف (مانند /health) */
    public function __construct(
        private readonly string $downFile = '',
        private readonly array $except = [],
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $downFile = $this->downFile !== '' ? $this->downFile : (\defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/storage/framework/down' : '');

        if (\is_file($downFile)) {
            foreach ($this->except as $pattern) {
                if ($request->is($pattern)) {
                    return $next($request);
                }
            }

            /** @var array{retry?: int, message?: string} $data */
            $data = (array) (\json_decode((string) \file_get_contents($downFile), true) ?: []);

            $response = new Response(
                (string) ($data['message'] ?? 'سایت موقتاً در حالت تعمیر است. / Site is under maintenance.'),
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );

            return isset($data['retry']) ? $response->withHeader('Retry-After', (string) $data['retry']) : $response;
        }

        return $next($request);
    }
}
