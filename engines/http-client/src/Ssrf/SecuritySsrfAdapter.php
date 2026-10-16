<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Ssrf;

use Tavaan\Engine\Security\Security;

/**
 * تَوان | Tavaan — HttpClient
 * SecuritySsrfAdapter — اتصال SsrfGuard موتور security به کلاینت HTTP
 * (وابستگی نرم؛ DOC-064 §1).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class SecuritySsrfAdapter implements SsrfCheckerInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function assertAllowed(string $url): void
    {
        $this->security->assertSsrfAllowed($url);
    }

    public function isAllowed(string $url): bool
    {
        return $this->security->ssrfCheck($url);
    }
}
