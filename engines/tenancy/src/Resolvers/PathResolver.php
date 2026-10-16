<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Resolvers;

use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Tenancy\TenantResolverInterface;

/**
 * تَوان | Tavaan — Tenancy
 * PathResolver — تشخیص tenant از سگمنت مسیر (`/t/{identifier}/…`).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class PathResolver implements TenantResolverInterface
{
    public function __construct(private readonly string $prefix = 't')
    {
    }

    public function resolve(RequestInterface $request): ?string
    {
        if (!\method_exists($request, 'path')) {
            return null;
        }

        $segments = \array_values(\array_filter(\explode('/', (string) $request->path())));
        if (\count($segments) < 2 || $segments[0] !== $this->prefix) {
            return null;
        }

        $identifier = (string) $segments[1];

        return \preg_match('/^[A-Za-z0-9._-]{1,64}$/', $identifier) === 1 ? $identifier : null;
    }
}
