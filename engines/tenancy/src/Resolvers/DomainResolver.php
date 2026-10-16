<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Resolvers;

use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Tenancy\TenantResolverInterface;

/**
 * تَوان | Tavaan — Tenancy
 * DomainResolver — تشخیص tenant از subdomain یا دامنهٔ ثبت‌شده (DOC-028 §2.3).
 *
 * - `shop1.example.com` + domain_suffix=`.example.com` → `shop1`
 * - یا دامنهٔ کامل ثبت‌شده در رکورد tenant (از طریق callback)
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class DomainResolver implements TenantResolverInterface
{
    /**
     * @param string                       $suffix      پسوند دامنه (مثلاً `.example.com`)
     * @param \Closure(string): ?string|null $domainLookup نگاشت دامنهٔ کامل → identifier
     */
    public function __construct(
        private readonly string $suffix = '',
        private readonly ?\Closure $domainLookup = null,
    ) {
    }

    public function resolve(RequestInterface $request): ?string
    {
        $host = \method_exists($request, 'host') ? (string) $request->host() : '';
        $host = \mb_strtolower(\trim($host));
        if ($host === '') {
            return null;
        }

        // دامنهٔ کامل ثبت‌شده
        if ($this->domainLookup !== null) {
            $identifier = ($this->domainLookup)($host);
            if ($identifier !== null && $identifier !== '') {
                return $identifier;
            }
        }

        // subdomain + suffix
        if ($this->suffix !== '' && \str_ends_with($host, $this->suffix)) {
            $sub = \substr($host, 0, -\strlen($this->suffix));
            if ($sub !== '' && !\str_contains($sub, '.') && \preg_match('/^[a-z0-9-]{1,64}$/', $sub) === 1) {
                return $sub;
            }
        }

        return null;
    }
}
