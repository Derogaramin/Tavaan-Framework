<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Tenancy\TenantResolverInterface;

/**
 * تَوان | Tavaan — Tenancy
 * TenantResolverChain — زنجیرهٔ resolverها به ترتیب (DOC-028 §2.3).
 *
 * ترتیب پیش‌فرض: Domain → Header → Path → Token → Query → Default.
 * اولین resolver غیر-null برنده است.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantResolverChain
{
    /** @param list<TenantResolverInterface> $resolvers */
    public function __construct(private readonly array $resolvers = [])
    {
    }

    public function resolve(RequestInterface $request): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $id = $resolver->resolve($request);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /** @return list<TenantResolverInterface> */
    public function resolvers(): array
    {
        return $this->resolvers;
    }
}
