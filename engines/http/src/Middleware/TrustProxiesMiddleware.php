<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Request;

/**
 * تَوان | Tavaan — Http
 * TrustProxiesMiddleware — پذیرش X-Forwarded-* فقط از پروکسی‌های allowlist.
 *
 * 🔒 پیش‌فرض trusted_proxies = [] یعنی هیچ header فورواردی پذیرفته نمی‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class TrustProxiesMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $trusted       CIDR یا `*`
     * @param list<string> $trustedHeaders
     */
    public function __construct(
        private readonly array $trusted = [],
        private readonly array $trustedHeaders = ['X-Forwarded-For', 'X-Forwarded-Host', 'X-Forwarded-Proto', 'X-Forwarded-Port'],
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        if ($this->trusted === [] || !$request instanceof Request) {
            return $next($request);
        }

        $remoteIp = (string) ($request->server()->get('REMOTE_ADDR') ?? '');
        if (!$this->isTrusted($remoteIp)) {
            return $next($request);
        }

        $cloned = $request;

        if (\in_array('X-Forwarded-For', $this->trustedHeaders, true)) {
            $forwarded = (string) ($request->header('X-Forwarded-For') ?? '');
            if ($forwarded !== '') {
                $chain = \array_map(\trim(...), \explode(',', $forwarded));
                $realIp = $this->firstUntrusted(\array_reverse($chain));
                if ($realIp !== null) {
                    $cloned = $cloned->withClientIp($realIp);
                }
            }
        }

        if (\in_array('X-Forwarded-Proto', $this->trustedHeaders, true)
            && \strtolower((string) $request->header('X-Forwarded-Proto')) === 'https') {
            $cloned = $cloned->withSecure(true);
        }

        if (\in_array('X-Forwarded-Host', $this->trustedHeaders, true)) {
            $host = (string) $request->header('X-Forwarded-Host');
            if ($host !== '') {
                $cloned = $cloned->withHost(\explode(',', $host)[0]);
            }
        }

        return $next($cloned);
    }

    private function isTrusted(string $ip): bool
    {
        foreach ($this->trusted as $cidr) {
            if (IpMatcher::matches($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $chainFromRight
     */
    private function firstUntrusted(array $chainFromRight): ?string
    {
        foreach ($chainFromRight as $ip) {
            if (!$this->isTrusted($ip)) {
                return \filter_var($ip, FILTER_VALIDATE_IP) === false ? null : $ip;
            }
        }

        return null;
    }
}
