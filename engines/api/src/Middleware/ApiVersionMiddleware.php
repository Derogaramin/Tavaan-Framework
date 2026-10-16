<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Middleware;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Api
 * ApiVersionMiddleware — نگارش API با سه استراتژی url/header/query (DOC-044 §3).
 *
 * - `Route::middleware('api.version:2')` → نگارش اجباری v2
 * - url:     /api/v1/…
 * - header:  ‏Accept: application/vnd.tavaan.v2+json (یا X-Api-Version: v2)
 * - query:   ?version=v2
 *
 * 🔒 نگارش‌های خارج از `supported` → 404 با RFC 9457.
 * 🔒 نگارش‌های deprecated → هدرهای `Deprecation` و `Sunset`.
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class ApiVersionMiddleware implements MiddlewareInterface
{
    /** @param array<string, mixed>|null $config snapshot (null = خواندن زنده از container) */
    public function __construct(
        private readonly ?ConfigRepository $config = null,
        private readonly ?array $staticConfig = null,
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $config = $this->versioningConfig();

        $strategy = (string) ($config['strategy'] ?? 'url');
        $default = $this->normalizeVersion((string) ($config['default'] ?? 'v1'));
        $supported = \array_map($this->normalizeVersion(...), (array) ($config['supported'] ?? ['v1']));
        $deprecated = \array_map($this->normalizeVersion(...), (array) ($config['deprecated'] ?? []));

        // ۱) پارامتر صریح middleware (api.version:2) بالاترین اولویت را دارد
        $version = $params !== [] ? $this->normalizeVersion($params[0]) : null;

        // ۲) استراتژی
        $version ??= match ($strategy) {
            'header' => $this->fromHeader($request, $config) ?? $this->fromUrl($request) ?? $this->fromQuery($request),
            'query' => $this->fromQuery($request) ?? $this->fromUrl($request) ?? $this->fromHeader($request, $config),
            default => $this->fromUrl($request) ?? $this->fromHeader($request, $config) ?? $this->fromQuery($request),
        };

        $version ??= $default;

        // 🔒 فقط نگارش‌های پشتیبانی‌شده
        if (!\in_array($version, $supported, true)) {
            return $this->problem(
                'Unsupported API version',
                404,
                ['detail' => 'Version ' . $version . ' is not supported.', 'supported' => $supported],
            );
        }

        // تزریق در request برای استفادهٔ controllerها
        if ($request instanceof Request) {
            $request = $request->withAttribute('api_version', $version);
        }

        /** @var ResponseInterface $response */
        $response = $next($request);

        if ($response instanceof Response) {
            $response = $response->withHeader('X-Api-Version', $version);

            // 🔒 نگارش منسوخ → Deprecation (+ Sunset اگر پیکربندی شده باشد)
            if (\in_array($version, $deprecated, true)) {
                $response = $response->withHeader('Deprecation', 'true');
                $sunsets = (array) ($config['sunset'] ?? []);
                $sunset = $sunsets[$version] ?? null;
                if (\is_string($sunset) && $sunset !== '') {
                    $response = $response->withHeader('Sunset', $sunset);
                }
            }
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function versioningConfig(): array
    {
        if ($this->staticConfig !== null) {
            return $this->staticConfig;
        }
        if ($this->config !== null) {
            return (array) $this->config->get('api.versioning', []);
        }

        return [];
    }

    /** `2`/`v2` → `v2` */
    private function normalizeVersion(string $version): string
    {
        $version = \trim(\mb_strtolower($version));
        if ($version === '') {
            return '';
        }

        return \str_starts_with($version, 'v') ? $version : 'v' . $version;
    }

    private function fromUrl(RequestInterface $request): ?string
    {
        $path = '/' . \trim((string) (\method_exists($request, 'path') ? $request->path() : ''), '/');
        if (\preg_match('#^/[^/]+/(v\d+)(/|$)#', $path, $m) === 1) {
            return $m[1];
        }
        if (\preg_match('#^/(v\d+)(/|$)#', $path, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function fromHeader(RequestInterface $request, array $config): ?string
    {
        // هدر اختصاصی ساده
        $direct = $request->header('X-Api-Version');
        if (\is_string($direct) && \preg_match('/^v?\d+$/', \trim($direct)) === 1) {
            return $this->normalizeVersion($direct);
        }

        // Accept: application/vnd.tavaan.v2+json
        $prefix = (string) ($config['prefix'] ?? 'application/vnd.tavaan.');
        $accept = (string) ($request->header('Accept') ?? '');
        foreach (\array_map(\trim(...), \explode(',', $accept)) as $part) {
            if (\str_starts_with($part, $prefix) && \preg_match('/\.(v\d+)\+/', $part, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    private function fromQuery(RequestInterface $request): ?string
    {
        if (!\method_exists($request, 'query')) {
            return null;
        }
        $v = $request->query('version') ?? $request->query('v');
        if (\is_string($v) && \preg_match('/^v?\d+$/', \trim($v)) === 1) {
            return $this->normalizeVersion($v);
        }

        return null;
    }

    /** پاسخ RFC 9457 (بدون وابستگی به ResponseFactory). */
    private function problem(string $title, int $status, array $extra = []): Response
    {
        $body = \json_encode(
            ['type' => 'about:blank', 'title' => $title, 'status' => $status, ...$extra],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '{}';

        return new Response($body, $status, ['Content-Type' => 'application/problem+json; charset=UTF-8']);
    }
}
