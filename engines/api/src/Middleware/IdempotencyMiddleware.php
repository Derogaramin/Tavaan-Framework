<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Middleware;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Api
 * IdempotencyMiddleware — پاسخ یک‌بار برای POSTهای بین‌سرویسی (DOC-044 §5).
 *
 * کلید `Idempotency-Key` + مسیر → پاسخ cache شده (پیش‌فرض ۲۴ ساعت).
 * 🔒 درخواست همزمان با کلید تکراری در جریان → 409 (in-progress).
 * 🔒 فقط پاسخ‌های status < 500 ذخیره می‌شوند (خطای سرور قابل replay نیست).
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class IdempotencyMiddleware implements MiddlewareInterface
{
    private int $ttl;

    public function __construct(
        private readonly ?CacheRepository $cache = null,
        int $ttl = 86400,
    ) {
        $this->ttl = \max(1, $ttl);
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || \trim($key) === '' || $this->cache === null) {
            return $next($request);
        }
        $key = \trim($key);
        if (\strlen($key) > 250) {
            return $this->problem('Invalid Idempotency-Key', 400, ['detail' => 'Idempotency-Key is too long (max 250 chars).']);
        }

        $path = \method_exists($request, 'path') ? \trim((string) $request->path(), '/') : '';
        // 🔒 کلید cache از hash ساخته می‌شود (نه ورودی خام کاربر)
        $cacheKey = 'idempotency:' . \sha1($path . '|' . $key);

        /** @var mixed $cached */
        $cached = $this->cache->get($cacheKey);
        if (\is_array($cached) && isset($cached['status'], $cached['body'])) {
            // 🔒 پاسخ قبلی — بدون اجرای دوبارهٔ handler
            return new Response(
                (string) $cached['body'],
                (int) $cached['status'],
                [...(array) ($cached['headers'] ?? []), 'X-Idempotent-Replay' => 'true'],
            );
        }

        // قفل «در جریان» — از اجرای موازی دوبارهٔ همان عملیات جلوگیری می‌کند
        $lockKey = $cacheKey . ':lock';
        if (!$this->cache->add($lockKey, 1, $this->ttl)) {
            return $this->problem('Idempotent request in progress', 409, [
                'detail' => 'A request with this Idempotency-Key is currently being processed.',
            ]);
        }

        try {
            /** @var ResponseInterface $response */
            $response = $next($request);

            if ($response->statusCode() < 500) {
                $this->cache->put($cacheKey, [
                    'status' => $response->statusCode(),
                    'headers' => $this->safeHeaders($response),
                    'body' => $response->body(),
                ], $this->ttl);
            }

            return $response;
        } finally {
            $this->cache->forget($lockKey);
        }
    }

    /** هدرهای قابل‌بازپخش (بدون هدرهای اتصال/لحظه‌ای). */
    private function safeHeaders(ResponseInterface $response): array
    {
        $skip = ['content-length', 'connection', 'transfer-encoding', 'date', 'set-cookie', 'x-idempotent-replay'];
        $out = [];
        foreach ((array) (\method_exists($response, 'headers') ? $response->headers() : []) as $name => $value) {
            if (\in_array(\mb_strtolower((string) $name), $skip, true)) {
                continue;
            }
            $out[(string) $name] = \is_array($value) ? (string) (\reset($value) ?: '') : (string) $value;
        }

        return $out;
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
