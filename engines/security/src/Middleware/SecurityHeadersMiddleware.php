<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\Middleware;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Security
 * SecurityHeadersMiddleware — هدرهای امنیتی از config/security.php (DOC-059 §2.3).
 *
 * 🔒 غیرفعال‌سازی هر هدر (null) → هشدار لاگ.
 * 🔒 X-Powered-By/Server همیشه حذف.
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, mixed> $headers هدرهای صریح (بدون config)
     */
    public function __construct(
        private readonly ?RepositoryInterface $config = null,
        private readonly array $headers = [],
        private readonly bool $override = false,
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $response = $next($request);

        foreach ($this->resolveHeaders($request) as $name => $value) {
            if ($value !== null && (!$response->hasHeader($name) || $this->override)) {
                $response = $response->withHeader($name, $value);
            }
        }

        if ($response instanceof Response) {
            $response = $response->withoutHeader('X-Powered-By')->withoutHeader('Server');
        }

        return $response;
    }

    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
    }

    /** @return array<string, string|null> */
    private function resolveHeaders(RequestInterface $request): array
    {
        if ($this->headers !== []) {
            return $this->headers;
        }

        $get = fn (string $key, mixed $default = null): mixed => $this->config?->get('security.headers.' . $key, $default) ?? $default;
        $secure = \method_exists($request, 'isSecure') ? $request->isSecure() : false;

        $hsts = (string) $get('hsts', 'max-age=31536000; includeSubDomains');

        return [
            'Content-Security-Policy' => $get('csp'),
            // 🔒 HSTS فقط روی HTTPS معنا دارد
            'Strict-Transport-Security' => $secure ? $hsts : null,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => (string) $get('frame', 'DENY'),
            'Referrer-Policy' => (string) $get('referrer', 'strict-origin-when-cross-origin'),
            'Permissions-Policy' => $get('permissions'),
            'Cross-Origin-Opener-Policy' => (string) $get('coop', 'same-origin'),
            'Cross-Origin-Resource-Policy' => (string) $get('corp', 'same-origin'),
        ];
    }
}
