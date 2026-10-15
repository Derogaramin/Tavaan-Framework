<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Middleware;

use Tavaan\Core\Context\TraceContext;
use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Http
 * TraceContextMiddleware — پارس/تولید W3C traceparent و تزریق TraceContext.
 *
 * @api 1.0
 * @see docs/02-architecture/request-lifecycle.md
 */
final class TraceContextMiddleware implements MiddlewareInterface
{
    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $traceparent = (string) ($request->header('traceparent') ?? '');

        if (\preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/', $traceparent, $m) === 1) {
            TraceContext::setTrace($m[1], $m[2]);
        } else {
            TraceContext::reset();
            TraceContext::traceId();
        }

        $response = $next($request);

        if ($response instanceof Response) {
            $response = $response->withHeader(
                'traceparent',
                \sprintf('00-%s-%s-01', TraceContext::traceId(), TraceContext::spanId()),
            );
        }

        return $response;
    }
}
