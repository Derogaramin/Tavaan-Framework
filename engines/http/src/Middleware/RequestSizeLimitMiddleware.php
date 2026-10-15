<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Exceptions\PayloadTooLargeException;

/**
 * تَوان | Tavaan — Http
 * RequestSizeLimitMiddleware — ۴۱۳ برای body بزرگ‌تر از حد.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class RequestSizeLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly int $maxBytes = 8_388_608)
    {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $length = (int) ($request->header('Content-Length') ?? 0);
        if ($length > $this->maxBytes) {
            throw PayloadTooLargeException::for($length, $this->maxBytes);
        }

        return $next($request);
    }
}
