<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Core\Exceptions\Http\CsrfTokenMismatchException;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Security\Csrf\CsrfManager;
use Tavaan\Engine\Session\Session;

/**
 * تَوان | Tavaan — Security
 * CsrfVerifyMiddleware — نسخهٔ config-driven موتور security (DOC-059 §2.1).
 *
 * منابع توکن: input `_token`، header `X-CSRF-TOKEN`، کوکی رمزنگاری‌شدهٔ
 * `XSRF-TOKEN` (double-submit برای API stateless).
 *
 * ⚠️ نسخهٔ M2 در موتور session باقی است؛ این نسخه except را از
 * config/security.php می‌خواند و double-submit را پشتیبانی می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class CsrfVerifyMiddleware implements MiddlewareInterface
{
    /** @var list<string> متدهای معاف (RFC-safe) */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param array<string, mixed> $config security.csrf
     */
    public function __construct(
        private readonly CsrfManager $csrf,
        private readonly array $config = [],
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        if (!(bool) ($this->config['enabled'] ?? true)
            || \in_array($request->method(), self::SAFE_METHODS, true)
            || $this->isExcluded($request)) {
            return $next($request);
        }

        // stateless double-submit (کوکی رمزنگاری‌شده + هدر)
        if ($request instanceof Request && (bool) ($this->config['encrypted_cookie'] ?? false)) {
            $cookie = $request->cookie('XSRF-TOKEN');
            $header = $request->header('X-XSRF-TOKEN');
            if (\is_string($cookie) && \is_string($header)
                && $this->csrf->verifyDoubleSubmit($cookie, $header)) {
                return $next($request);
            }
        }

        $token = $this->tokenFrom($request);

        if ($token === null || !$this->csrf->verify($token)) {
            throw new CsrfTokenMismatchException();   // → 419
        }

        return $next($request);
    }

    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
    }

    private function tokenFrom(RequestInterface $request): ?string
    {
        if (!$request instanceof Request) {
            return null;
        }

        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');

        return \is_string($token) && $token !== '' ? $token : null;
    }

    private function isExcluded(RequestInterface $request): bool
    {
        $path = \method_exists($request, 'path') ? '/' . \ltrim($request->path(), '/') : '';

        foreach ((array) ($this->config['except'] ?? []) as $pattern) {
            $pattern = '/' . \ltrim((string) $pattern, '/');   // نرمال‌سازی: با / شروع شود
            if ($pattern === $path) {
                return true;
            }
            if (\str_contains($pattern, '*')) {
                $regex = '/^' . \str_replace('\*', '.*', \preg_quote($pattern, '/')) . '$/';
                if (\preg_match($regex, $path) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
