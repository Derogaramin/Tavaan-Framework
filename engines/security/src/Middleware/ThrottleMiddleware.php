<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Core\Exceptions\Http\TooManyRequestsException;
use Tavaan\Engine\Security\RateLimit\RateLimiter;

/**
 * تَوان | Tavaan — Security
 * ThrottleMiddleware — `throttle:60,1` (۶۰ درخواست در دقیقه — DOC-059 §2.2).
 *
 * 🔒 پاسخ ۴۲۹ با `Retry-After` و `X-RateLimit-*`.
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class ThrottleMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $maxAttempts = (int) ($params[0] ?? 60);
        $decayMinutes = (float) ($params[1] ?? 1);
        $decaySeconds = (int) \max(1, \round($decayMinutes * 60));

        $key = $this->resolveKey($request);

        if (!$this->limiter->attempt($key, $maxAttempts, $decaySeconds)) {
            $retryAfter = \max(1, $this->limiter->availableIn($key, $decaySeconds));

            throw new TooManyRequestsException(
                context: ['retry_after' => $retryAfter],
                headers: [
                    'Retry-After' => (string) $retryAfter,
                    'X-RateLimit-Limit' => (string) $maxAttempts,
                    'X-RateLimit-Remaining' => '0',
                ],
            );
        }

        /** @var ResponseInterface $response */
        $response = $next($request);

        $remaining = $this->limiter->remaining($key, $maxAttempts, $decaySeconds);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
    }

    private function resolveKey(RequestInterface $request): string
    {
        $identifier = '';
        if (\method_exists($request, 'ip')) {
            $identifier = (string) ($request->ip() ?? '');
        }
        $path = \method_exists($request, 'path') ? $request->path() : '';

        return 'throttle:' . $path . ':' . ($identifier !== '' ? $identifier : 'cli');
    }
}
