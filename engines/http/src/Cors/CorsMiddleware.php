<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Cors;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Core\Exceptions\Core\ConfigException;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Cors
 * CorsMiddleware — Preflight (OPTIONS) + هدرهای CORS با Origin allowlist.
 *
 * 🔒 `*` با credentials=true → خطای پیکربندی (RFC نقض می‌شود).
 * 🔒 همیشه `Vary: Origin` (Cache Poisoning).
 *
 * @api 1.0
 * @see docs/03-engines/cors.md
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config = [])
    {
        $allowed = (array) ($config['allowed_origins'] ?? []);
        $credentials = (bool) ($config['supports_credentials'] ?? false);

        if (\in_array('*', $allowed, true) && $credentials) {
            throw new ConfigException(
                messageId: 'cors.wildcard_with_credentials',
                hint: 'allowed_origins=* با supports_credentials=true مجاز نیست (RFC 6265/9110).',
            );
        }
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        if (!$this->matchesPaths($request)) {
            return $next($request);
        }

        // Preflight
        if ($request->method() === 'OPTIONS' && ($request->header('Access-Control-Request-Method') !== null)) {
            return $this->handlePreflight($request);
        }

        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    private function matchesPaths(RequestInterface $request): bool
    {
        $paths = (array) ($this->config['paths'] ?? ['*']);
        if ($paths === [] || \in_array('*', $paths, true)) {
            return true;
        }

        $path = \trim($request->path(), '/');
        foreach ($paths as $pattern) {
            $regex = '/^' . \str_replace('\*', '.*', \preg_quote(\trim((string) $pattern, '/'), '/')) . '$/';
            if (\preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function addCorsHeaders(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->header('Origin');
        if ($origin === null || !$this->isAllowed($origin) || !$response instanceof Response) {
            return $response;
        }

        $credentials = (bool) ($this->config['supports_credentials'] ?? false);
        $exposed = (array) ($this->config['exposed_headers'] ?? []);

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $this->reflectOrigin($origin))
            ->withHeader('Vary', 'Origin', false)
            ->withHeader('Access-Control-Allow-Credentials', $credentials ? 'true' : 'false');

        if ($exposed !== []) {
            $response = $response->withHeader('Access-Control-Expose-Headers', \implode(', ', $exposed));
        }

        return $response;
    }

    private function handlePreflight(RequestInterface $request): ResponseInterface
    {
        $origin = (string) ($request->header('Origin') ?? '');
        $method = (string) ($request->header('Access-Control-Request-Method') ?? '');
        $headers = $request->header('Access-Control-Request-Headers');

        if ($origin === '' || !$this->isAllowed($origin) || !$this->isMethodAllowed($method)) {
            return new Response('', 403);
        }

        $allowedHeaders = (array) ($this->config['allowed_headers'] ?? ['*']);
        $allowedMethods = (array) ($this->config['allowed_methods'] ?? ['*']);

        return (new Response('', 204))
            ->withHeader('Access-Control-Allow-Origin', $this->reflectOrigin($origin))
            ->withHeader('Access-Control-Allow-Methods', \in_array('*', $allowedMethods, true)
                ? 'GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD'
                : \implode(', ', $allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', $headers ?: \implode(', ', $allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) ($this->config['max_age'] ?? 3600))
            ->withHeader('Access-Control-Allow-Credentials', (bool) ($this->config['supports_credentials'] ?? false) ? 'true' : 'false')
            ->withHeader('Vary', 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
    }

    private function isAllowed(string $origin): bool
    {
        $allowed = (array) ($this->config['allowed_origins'] ?? []);
        if (\in_array('*', $allowed, true)) {
            return true;
        }

        foreach ($allowed as $pattern) {
            $pattern = (string) $pattern;
            if ($origin === $pattern) {
                return true;
            }
            if (\str_contains($pattern, '*')) {
                $regex = '#^' . \str_replace('\*', '[^.]+', \preg_quote($pattern, '#')) . '$#';
                if (\preg_match($regex, $origin) === 1) {
                    return true;
                }
            }
            if (\str_starts_with($pattern, '*://')) {
                $host = \substr($pattern, 4);
                if (\str_ends_with($origin, '://' . $host)) {
                    return true;
                }
            }
        }

        foreach ((array) ($this->config['allowed_origins_patterns'] ?? []) as $regex) {
            if (\is_string($regex) && @\preg_match($regex, $origin) === 1) {
                return true;
            }
        }

        return false;
    }

    /** 🔒 با `*` بدون credentials → خود `*`؛ در غیر این صورت reflect origin. */
    private function reflectOrigin(string $origin): string
    {
        $allowed = (array) ($this->config['allowed_origins'] ?? []);
        $credentials = (bool) ($this->config['supports_credentials'] ?? false);

        if (\in_array('*', $allowed, true) && !$credentials) {
            return '*';
        }

        return $origin;
    }

    private function isMethodAllowed(string $method): bool
    {
        $allowed = (array) ($this->config['allowed_methods'] ?? ['*']);

        return \in_array('*', $allowed, true) || \in_array(\strtoupper($method), \array_map(\strtoupper(...), $allowed), true);
    }
}
