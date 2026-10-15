<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Http;

/**
 * قرارداد Middleware وب (DOC-014 §2.3).
 *
 * terminate() اختیاری است (Kernel با instanceof بررسی می‌کند).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
interface MiddlewareInterface
{
    /**
     * @param \Closure(RequestInterface): ResponseInterface $next
     */
    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface;
}
