<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Http
 * SecurityHeadersMiddleware — هدرهای امنیتی پیش‌فرض (AC-HTTP-04).
 *
 * @api 1.0
 * @see docs/01-standards/security-policy.md
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, string> $headers هدرهای پیکربندی‌شده
     */
    public function __construct(
        private readonly array $headers = [],
        private readonly bool $override = false,
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $response = $next($request);

        if (!$response instanceof Response) {
            return $response;
        }

        $headers = $this->headers !== [] ? $this->headers : self::defaults($request->isSecure());

        foreach ($headers as $name => $value) {
            if (!$response->hasHeader($name) || $this->override) {
                $response = $response->withHeader($name, $value);
            }
        }

        // 🔒 Information Leak
        $response = $response->withoutHeader('X-Powered-By')->withoutHeader('Server');

        return $response;
    }

    /** @return array<string, string> */
    public static function defaults(bool $secure = false): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-XSS-Protection' => '0',   // مدرن: CSP؛ مقدار 0 توصیهٔ فعلی
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
        ];

        if ($secure) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }
}
