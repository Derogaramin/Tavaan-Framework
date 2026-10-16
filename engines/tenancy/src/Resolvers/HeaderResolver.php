<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Resolvers;

use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Tenancy\TenantResolverInterface;

/**
 * تَوان | Tavaan — Tenancy
 * HeaderResolver — تشخیص tenant از هدر (پیش‌فرض X-Tenant-Id).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class HeaderResolver implements TenantResolverInterface
{
    public function __construct(private readonly string $header = 'X-Tenant-Id')
    {
    }

    public function resolve(RequestInterface $request): ?string
    {
        $value = $request->header($this->header);
        if (!\is_string($value) || \trim($value) === '') {
            return null;
        }

        $value = \trim($value);

        // 🔒 فقط identifier امن (جلوگیری از log injection/overflow)
        return \preg_match('/^[A-Za-z0-9._-]{1,64}$/', $value) === 1 ? $value : null;
    }
}
