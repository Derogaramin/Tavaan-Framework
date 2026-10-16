<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade چنداجاره‌ای (Tenant — DOC-082E).
 *
 * @method static \Tavaan\Core\Contracts\Tenancy\TenantInterface|null current()
 * @method static int|string|null id()
 * @method static string|null identifier()
 * @method static void setCurrent(?\Tavaan\Core\Contracts\Tenancy\TenantInterface $tenant)
 * @method static mixed run(\Tavaan\Core\Contracts\Tenancy\TenantInterface $tenant, callable $callback)
 * @method static mixed withoutCurrentScope(callable $callback, ?string $reason = null)
 * @method static \Tavaan\Core\Contracts\Tenancy\TenantInterface|null findByIdentifier(string $identifier)
 * @method static \Tavaan\Core\Contracts\Tenancy\TenantInterface|null findByDomain(string $domain)
 * @method static \Tavaan\Core\Contracts\Tenancy\TenantInterface|null find(int|string $id)
 * @method static \Tavaan\Core\Contracts\Tenancy\TenantInterface create(array $data)
 * @method static bool update(int|string|\Tavaan\Core\Contracts\Tenancy\TenantInterface $tenant, array $data)
 * @method static bool activate(int|string|\Tavaan\Core\Contracts\Tenancy\TenantInterface $tenant)
 * @method static bool deactivate(int|string|\Tavaan\Core\Contracts\Tenancy\TenantInterface $tenant)
 * @method static bool delete(int|string|\Tavaan\Core\Contracts\Tenancy\TenantInterface $tenant)
 * @method static list<\Tavaan\Core\Contracts\Tenancy\TenantInterface> all(int $limit = 500, int $offset = 0)
 * @method static list<\Tavaan\Core\Contracts\Tenancy\TenantInterface> active(int $limit = 500)
 * @method static int countActive()
 * @method static array|null quota(string $resource, ?\Tavaan\Core\Contracts\Tenancy\TenantInterface $tenant = null)
 * @method static string storagePath(?string $identifier = null)
 * @method static bool enabled()
 * @method static string strategy()
 * @method static string tenantColumn()
 * @method static void flushMemo()
 * @mixin \Tavaan\Engine\Tenancy\TenantManager
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class Tenant extends Facade
{
    protected static function accessor(): string
    {
        return 'tenant';
    }
}
