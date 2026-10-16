<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Core\Contracts\Http\TerminableMiddleware;
use Tavaan\Core\Contracts\Tenancy\TenantInterface;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Tenancy\TenantContext;
use Tavaan\Engine\Tenancy\TenantManager;
use Tavaan\Engine\Tenancy\TenantResolverChain;
use Tavaan\Engine\Tenancy\Exceptions\TenantNotFoundException;
use Tavaan\Engine\Tenancy\Exceptions\TenantSuspendedException;

/**
 * تَوان | Tavaan — Tenancy
 * IdentifyTenantMiddleware — resolve/اعتبارسنجی/ست‌کردن TenantContext (DOC-028 §2.3).
 *
 * زنجیره: resolverها → یافتن tenant → بررسی active → set context →
 * اتصال استراتژی B/C → در terminate پاک‌سازی (TEN-SEC-05).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class IdentifyTenantMiddleware implements MiddlewareInterface, TerminableMiddleware
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly TenantResolverChain $chain,
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $identifier = $this->chain->resolve($request);

        if ($identifier === null) {
            throw new TenantNotFoundException(context: ['hint' => 'هیچ resolverای tenant را تشخیص نداد']);
        }

        $tenant = $this->tenants->findByIdentifier($identifier)
            ?? $this->tenants->findByDomain($identifier);

        if ($tenant === null) {
            throw new TenantNotFoundException(context: ['identifier' => $identifier]);
        }
        if (!$tenant->isActive()) {
            throw new TenantSuspendedException(context: ['identifier' => $tenant->identifier()]);
        }

        TenantContext::set($tenant);
        $this->tenants->dispatchResolved($tenant, 'middleware');

        // استراتژی B/C → اتصال DB مخصوص tenant
        $this->tenants->connectStrategy($tenant);

        if ($request instanceof Request) {
            $request = $request->withAttribute('tenant', $tenant);
            $request = $request->withAttribute('tenant_id', $tenant->id());
        }

        return $next($request);
    }

    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
        // 🔒 TEN-SEC-05: پاک‌سازی context پس از هر درخواست (Resident safety)
        $this->tenants->disconnectStrategy();
        TenantContext::clear();
    }
}
