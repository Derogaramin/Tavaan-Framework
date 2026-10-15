<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Cookie;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Crypt\Crypt;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Cookie
 * EncryptCookiesMiddleware — decrypt کوکی‌های ورودی + encrypt کوکی‌های صف‌شده.
 *
 * 🔒 کوکی دستکاری‌شده (MAC نامعتبر) → بی‌صدا null (DOC-042 §3).
 *
 * @api 1.0
 * @see docs/03-engines/cookie.md
 */
final class EncryptCookiesMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $except کوکی‌هایی که رمزنگاری نمی‌شوند (XSRF-TOKEN و …)
     */
    public function __construct(
        private readonly CookieJar $jar,
        private readonly ?Crypt $crypt = null,
        private readonly array $except = [],
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $decrypted = [];
        foreach ($request->cookies() as $name => $value) {
            if (\in_array($name, $this->except, true) || !\is_string($value)) {
                $decrypted[$name] = $value;
                continue;
            }

            $decrypted[$name] = $this->decryptValue($name, $value);
        }

        $cloned = $request instanceof Request ? $request->withCookies($decrypted) : $request;
        $response = $next($cloned);

        if (!$response instanceof Response) {
            return $response;
        }

        foreach ($this->jar->queued() as $cookie) {
            if ($cookie->encrypted && $this->crypt !== null && !\in_array($cookie->name, $this->except, true)) {
                $cookie = $cookie->withValue($this->crypt->encrypt($cookie->value, 'cookie:' . $cookie->name));
            }
            $response = $response->cookie($cookie);
        }
        $this->jar->flushQueued();

        return $response;
    }

    private function decryptValue(string $name, string $value): ?string
    {
        if ($this->crypt === null || !\str_starts_with($value, 'v') || !\str_contains($value, ':')) {
            return $value;
        }

        try {
            return $this->crypt->decrypt($value, 'cookie:' . $name);
        } catch (\Throwable) {
            return null;   // 🔒 tampered → null
        }
    }
}
