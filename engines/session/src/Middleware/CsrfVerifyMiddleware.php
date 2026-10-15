<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Core\Exceptions\Http\CsrfTokenMismatchException;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Session\Session;

/**
 * تَوان | Tavaan — Session
 * CsrfVerifyMiddleware — بررسی توکن CSRF در متدهای تغییردهنده (۴۱۹).
 *
 * منابع توکن: input `_token`، header `X-CSRF-TOKEN`، کوکی `XSRF-TOKEN`.
 * 🔒 مقایسه با hash_equals (timing-safe).
 *
 * ⚠️ در M4 به موتور security منتقل می‌شود (DOC-059)؛ فعل‌اً بخشی از
 * «Session secure + CSRF-ready» در M2 است.
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class CsrfVerifyMiddleware implements MiddlewareInterface
{
    /** @var list<string> متدهای معاف (RFC-safe) */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param list<string> $except مسیرهای معاف (مانند webhookها)
     */
    public function __construct(private readonly array $except = [])
    {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        if (!$request instanceof Request
            || \in_array($request->method(), self::SAFE_METHODS, true)
            || !$request->hasSession()
            || $this->inExcept($request)) {
            return $next($request);
        }

        $session = $request->session();
        if (!$session instanceof Session) {
            return $next($request);
        }

        $token = $this->tokenFrom($request);

        if ($token === null || !\hash_equals($session->token(), $token)) {
            throw new CsrfTokenMismatchException();   // → 419
        }

        return $next($request);
    }

    private function tokenFrom(Request $request): ?string
    {
        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');
        if (\is_string($token) && $token !== '') {
            return $token;
        }

        // کوکی XSRF-TOKEN (برای SPA) — توسط EncryptCookiesMiddleware decrypt شده
        $xsrf = $request->cookie('XSRF-TOKEN');

        return \is_string($xsrf) && $xsrf !== '' ? $xsrf : null;
    }

    private function inExcept(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
