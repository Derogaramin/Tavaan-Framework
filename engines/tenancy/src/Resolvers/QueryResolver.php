<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Resolvers;

use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Tenancy\TenantResolverInterface;

/**
 * تَوان | Tavaan — Tenancy
 * QueryResolver — تشخیص tenant از `?tenant=…` (⚠️ فقط dev).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class QueryResolver implements TenantResolverInterface
{
    public function __construct(
        private readonly string $param = 'tenant',
        private readonly bool $devOnly = true,
    ) {
    }

    public function resolve(RequestInterface $request): ?string
    {
        if ($this->devOnly && !$this->isDevEnvironment()) {
            return null;
        }
        if (!\method_exists($request, 'query')) {
            return null;
        }

        $value = $request->query($this->param);
        if (!\is_string($value) || \trim($value) === '') {
            return null;
        }

        $value = \trim($value);

        return \preg_match('/^[A-Za-z0-9._-]{1,64}$/', $value) === 1 ? $value : null;
    }

    private function isDevEnvironment(): bool
    {
        $env = $_SERVER['TVN_APP_ENV'] ?? $_ENV['TVN_APP_ENV'] ?? 'production';

        return \in_array((string) $env, ['local', 'development', 'dev', 'testing'], true);
    }
}
