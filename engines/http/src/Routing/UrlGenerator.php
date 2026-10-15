<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

use Tavaan\Engine\Crypt\Crypt;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Routing\Exceptions\UrlGenerationException;

/**
 * تَوان | Tavaan — Routing
 * UrlGenerator — ساخت URL از نام روت + Signed URL (DOC-040 §8).
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class UrlGenerator
{
    private ?Request $requestContext = null;

    public function __construct(
        private readonly Router $router,
        private readonly ?Crypt $crypt = null,
        private readonly string $appUrl = '',
    ) {
    }

    public function setRequestContext(?Request $request): void
    {
        $this->requestContext = $request;
    }

    /**
     * ساخت URL یک روت نام‌دار.
     *
     * @param array<string, mixed>|object $parameters
     */
    public function route(string $name, array|object $parameters = [], bool $absolute = true): string
    {
        $route = $this->router->routes()->getByName($name);

        if (\is_object($parameters) && !\is_array($parameters)) {
            // UrlRoutable-like: getRouteKey()
            $parameters = \method_exists($parameters, 'getRouteKey')
                ? [\ltrim(\str_replace('.', '_', $name), '_') => $parameters->getRouteKey()]
                : (array) $parameters;
        }

        $params = (array) $parameters;
        $path = '';
        $missing = [];

        foreach ($route->segments() as $segment) {
            if (isset($segment['static'])) {
                $path .= '/' . $segment['static'];
                continue;
            }

            $paramName = $segment['name'];
            if (\array_key_exists($paramName, $params)) {
                $value = $params[$paramName];
                $value = \is_object($value) && \method_exists($value, 'getRouteKey') ? $value->getRouteKey() : $value;
                unset($params[$paramName]);
                $path .= '/' . \rawurlencode((string) $value);
                continue;
            }

            $default = $route->getDefaults()[$paramName] ?? null;
            if ($default !== null) {
                $path .= '/' . \rawurlencode((string) $default);
                continue;
            }

            // پارامتر اختیاری (regex شامل ? نیست) — اگر default ندارد، missing
            if (($segment['wildcard'] ?? false) === true) {
                continue;
            }
            $missing[] = $paramName;
        }

        if ($missing !== []) {
            throw UrlGenerationException::missingParameters($name, $missing);
        }

        $url = $path === '' ? '/' : $path;

        // پارامترهای اضافه → query string
        if ($params !== []) {
            $url .= '?' . \http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $absolute ? $this->toAbsolute($url) : $url;
    }

    /** URL مطلق برای مسیر دلخواه. */
    public function url(string $path, bool $secure = false): string
    {
        if (\preg_match('#^[a-z][a-z0-9+.\-]*://#i', $path) === 1) {
            return $path;   // قبلاً مطلق
        }

        return $this->toAbsolute('/' . \ltrim($path, '/'), $secure);
    }

    /** URL asset (از config app.asset_url یا app.url). */
    public function asset(string $path): string
    {
        return $this->url($path);
    }

    public function current(): string
    {
        return $this->requestContext?->url() ?? $this->appUrl;
    }

    public function full(): string
    {
        return $this->requestContext?->fullUrl() ?? $this->appUrl;
    }

    public function previous(string $fallback = '/'): string
    {
        $session = $this->requestContext?->session();
        if ($session !== null && \method_exists($session, 'previousUrl')) {
            $previous = $session->previousUrl();
            if (\is_string($previous) && $previous !== '') {
                return $previous;
            }
        }

        return $this->requestContext?->referer() ?: $fallback;
    }

    // ─── Signed URL (DOC-040 §8.2) ────────────────────────────────────────

    public function signed(string $name, array $parameters = [], ?\DateTimeImmutable $expiresAt = null): string
    {
        $url = $this->route($name, $parameters);

        return $this->signUrl($url, $expiresAt);
    }

    public function temporarySignedRoute(string $name, \DateTimeImmutable $expiresAt, array $parameters = []): string
    {
        return $this->signed($name, $parameters, $expiresAt);
    }

    public function isValidSignature(Request $request): bool
    {
        if ($this->crypt === null) {
            return false;
        }

        $signature = (string) ($request->query('signature') ?? '');
        $expires = $request->query('expires');
        if ($signature === '') {
            return false;
        }
        if ($expires !== null && (int) $expires < \time()) {
            return false;
        }

        $query = $this->queryWithout($request, ['signature']);
        $payload = $this->normalizeUrlForSignature($request->url()) . ($query === '' ? '' : '?' . $query);

        return $this->crypt->verifyHmac($payload, $signature, 'signed_url');
    }

    public function hasValidSignature(Request $request): bool
    {
        return $this->isValidSignature($request);
    }

    private function signUrl(string $url, ?\DateTimeImmutable $expiresAt): string
    {
        if ($this->crypt === null) {
            throw new \LogicException('Signed URLs require the crypt engine (APP_KEY). Run `tvn key:generate`.');
        }

        $parts = \parse_url($url);
        $query = [];
        if (isset($parts['query'])) {
            \parse_str($parts['query'], $query);
        }
        if ($expiresAt !== null) {
            $query['expires'] = (string) $expiresAt->getTimestamp();
        }

        \ksort($query);   // 🔒 ترتیب قطعی
        $base = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '/');
        $payload = $base . ($query === [] ? '' : '?' . \http_build_query($query));

        $query['signature'] = $this->crypt->hmac($payload, 'signed_url');

        return $base . '?' . \http_build_query($query);
    }

    /** @param list<string> $exclude */
    private function queryWithout(Request $request, array $exclude): string
    {
        $query = $request->query();
        foreach ($exclude as $key) {
            unset($query[$key]);
        }
        \ksort($query);

        return $query === [] ? '' : \http_build_query($query);
    }

    private function normalizeUrlForSignature(string $url): string
    {
        $parts = \parse_url($url);

        return ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '/');
    }

    private function toAbsolute(string $url, ?bool $secure = null): string
    {
        if ($this->requestContext !== null) {
            $scheme = $secure === true ? 'https' : ($secure === false ? 'http' : $this->requestContext->scheme());
            $host = $this->requestContext->host();
            $port = $this->requestContext->port();
            $portSuffix = $port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
                ? ':' . $port
                : '';

            return $scheme . '://' . $host . $portSuffix . $url;
        }

        return \rtrim($this->appUrl, '/') . $url;
    }
}
