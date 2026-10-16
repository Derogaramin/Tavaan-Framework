<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Resolvers;

use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Tenancy\TenantResolverInterface;

/**
 * تَوان | Tavaan — Tenancy
 * DefaultResolver — tenant پیش‌فرض پیکربندی‌شده (آخرین حلقهٔ زنجیره).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class DefaultResolver implements TenantResolverInterface
{
    public function __construct(private readonly ?string $identifier = null)
    {
    }

    public function resolve(RequestInterface $request): ?string
    {
        return $this->identifier !== null && $this->identifier !== '' ? $this->identifier : null;
    }
}
