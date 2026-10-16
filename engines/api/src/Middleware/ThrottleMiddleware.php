<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Middleware;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Response;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Api
 * ThrottleMiddleware — محدودسازی نرخ درخواست (DOC-044 §6).
 *
 * ```php
 * Route::middleware('throttle:api')->group(...);   // limiter نام‌دار از config api.rate_limit.api
 * Route::middleware('throttle:60,1')->group(...);  // ۶۰ درخواست در دقیقه (سبک Laravel)
 * ```
 *
 * هویت کلید: کاربر احراز‌شده → API key (هدر X-Api-Key) → IP.
 * هدرها: X-RateLimit-Limit / X-RateLimit-Remaining / X-RateLimit-Reset
 * و در 429 هدر Retry-After.
 *
 * الگوریتم: پنجرهٔ ثابت با cache (add/increment) — اتمیک و بدون وابستگی.
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class ThrottleMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $cache = $this->cache();
        if ($cache === null) {
            // 🔒 بدون cache محدودسازی ممکن نیست — fail-open (رفتار مانند نبود middleware)
            return $next($request);
        }

        [$maxAttempts, $decaySeconds, $limiterName] = $this->parseParams($params);
        if ($maxAttempts <= 0) {
            return $next($request);
        }

        $identity = $this->identity($request);
        $key = 'throttle:' . $limiterName . ':' . \sha1($identity);
        $resetKey = $key . ':reset';

        $now = Clock::timestamp();
        /** @var mixed $resetAt */
        $resetAt = $cache->get($resetKey);
        if (!\is_int($resetAt) || $resetAt <= $now) {
            $resetAt = $now + $decaySeconds;
            $cache->put($resetKey, $resetAt, $decaySeconds + 1);
            $cache->forget($key);
        }

        // شمارش اتمیک: اولین بار add (با ttl)، بعد increment
        if (!$cache->add($key, 1, $decaySeconds + 1)) {
            $hits = (int) $cache->increment($key);
        } else {
            $hits = 1;
        }

        $remaining = \max(0, $maxAttempts - $hits);
        $retryAfter = \max(0, $resetAt - $now);

        if ($hits > $maxAttempts) {
            $response = $this->problem('Too Many Requests', 429, [
                'detail' => 'Rate limit exceeded. Try again in ' . $retryAfter . ' seconds.',
            ]);

            return $response
                ->withHeader('Retry-After', (string) $retryAfter)
                ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string) $resetAt);
        }

        /** @var ResponseInterface $response */
        $response = $next($request);

        if ($response instanceof Response) {
            $response = $response
                ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
                ->withHeader('X-RateLimit-Remaining', (string) $remaining)
                ->withHeader('X-RateLimit-Reset', (string) $resetAt);
        }

        return $response;
    }

    /**
     * `throttle:api` → limiter نام‌دار؛ `throttle:60,1` → ۶۰ در دقیقه.
     *
     * @param list<string> $params
     *
     * @return array{0: int, 1: int, 2: string}
     */
    private function parseParams(array $params): array
    {
        $first = $params[0] ?? 'default';

        if (\ctype_digit($first)) {
            $max = (int) $first;
            $decayMinutes = isset($params[1]) && \is_numeric($params[1]) ? (float) $params[1] : 1.0;

            return [$max, \max(1, (int) \round($decayMinutes * 60)), $max . '_' . $decayMinutes];
        }

        $limiters = [];
        if ($this->container->bound('config')) {
            $limiters = (array) $this->container->make('config')->get('api.rate_limit', []);
        }
        $limiter = (array) ($limiters[$first] ?? []);
        $max = (int) ($limiter['max'] ?? ($first === 'default' ? 60 : 0));
        $decay = (int) ($limiter['decay'] ?? 60);

        return [$max, \max(1, $decay), $first];
    }

    /** هویت برای کلید rate limit: user → api key → ip → global. */
    private function identity(RequestInterface $request): string
    {
        // ۱) کاربر احراز‌شده (TEN-SEC-10: per-user)
        try {
            if ($this->container->bound('auth')) {
                $auth = $this->container->make('auth');
                if (\method_exists($auth, 'id')) {
                    $id = $auth->id();
                    if ($id !== null) {
                        return 'user:' . $id;
                    }
                } elseif (\method_exists($auth, 'user')) {
                    $user = $auth->user();
                    if ($user !== null && \method_exists($user, 'getAuthIdentifier')) {
                        return 'user:' . $user->getAuthIdentifier();
                    }
                }
            }
        } catch (\Throwable) {
            // fall through
        }

        // ۲) کلید API
        $apiKey = $request->header('X-Api-Key');
        if (\is_string($apiKey) && \trim($apiKey) !== '') {
            return 'key:' . \trim($apiKey);
        }

        // ۳) IP
        $ip = \method_exists($request, 'ip') ? $request->ip() : null;
        if (\is_string($ip) && $ip !== '') {
            return 'ip:' . $ip;
        }

        return 'global';
    }

    private function cache(): ?CacheRepository
    {
        try {
            if ($this->container->bound(CacheRepository::class)) {
                $cache = $this->container->make(CacheRepository::class);

                return $cache instanceof CacheRepository ? $cache : null;
            }
            if ($this->container->bound('cache')) {
                $cache = $this->container->make('cache');
                if ($cache instanceof CacheRepository) {
                    return $cache;
                }
                if (\method_exists($cache, 'repository')) {
                    return $cache->repository();
                }
            }
        } catch (\Throwable) {
            // fail-open
        }

        return null;
    }

    private function problem(string $title, int $status, array $extra = []): Response
    {
        $body = \json_encode(
            ['type' => 'about:blank', 'title' => $title, 'status' => $status, ...$extra],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '{}';

        return new Response($body, $status, ['Content-Type' => 'application/problem+json; charset=UTF-8']);
    }
}
