<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\DebugBar;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Http\StreamedResponse;

/**
 * تَوان | Tavaan — Observability
 * DebugBarMiddleware — تزریق پنل Debug Bar قبل از </body> در پاسخ HTML.
 *
 * 🔒 فقط وقتی DebugBar->isEnabled() (app.debug و غیر production).
 * 🔒 فقط روی پاسخ‌های HTML با status 200 (نه stream، نه redirect).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class DebugBarMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly DebugBar $bar)
    {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $next($request);

        if (!$this->bar->isEnabled()) {
            return $response;
        }
        if (!$response instanceof Response || $response instanceof StreamedResponse) {
            return $response;
        }
        if ($response->statusCode() !== 200) {
            return $response;
        }
        $contentType = (string) ($response->header('Content-Type') ?? '');
        if (!\str_contains($contentType, 'text/html')) {
            return $response;
        }

        $body = $response->body();
        $pos = \strripos($body, '</body>');
        if ($pos === false) {
            return $response;
        }

        $injected = \substr($body, 0, $pos) . $this->bar->render() . \substr($body, $pos);

        return $response->withBody($injected);
    }
}
