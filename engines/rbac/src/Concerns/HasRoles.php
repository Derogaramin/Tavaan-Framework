<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac\Concerns;

use Tavaan\Core\Facade\Facade;
use Tavaan\Engine\Rbac\Gate;
use Tavaan\Engine\Rbac\RoleManager;

/**
 * تَوان | Tavaan — RBAC
 * HasRoles — trait کاربر: hasRole/hasPermission/can/assignRole (DOC-057 §5).
 *
 * روی مدل ORM یا GenericUser-سازگار استفاده می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
trait HasRoles
{
    private function rbacGate(): Gate
    {
        $gate = Facade::getFacadeApplication()->make('gate');
        if (!$gate instanceof Gate) {
            throw new \LogicException('RBAC engine (gate) is not available.');
        }

        return $gate;
    }

    private function rbacRoles(): RoleManager
    {
        $manager = Facade::getFacadeApplication()->make(RoleManager::class);
        if (!$manager instanceof RoleManager) {
            throw new \LogicException('RBAC engine (roles) is not available.');
        }

        return $manager;
    }

    /** شناسهٔ کاربر برای RBAC (Authenticatable). */
    abstract public function getAuthIdentifier(): int|string;

    public function hasRole(string $roleKey, ?int $tenantId = null): bool
    {
        return $this->rbacRoles()->hasRole($this, $roleKey, $tenantId);
    }

    /** @param list<string> $roleKeys */
    public function hasAnyRole(array $roleKeys, ?int $tenantId = null): bool
    {
        return $this->rbacRoles()->hasAnyRole($this, $roleKeys, $tenantId);
    }

    public function hasPermission(string $permission): bool
    {
        $resolver = $this->rbacGate()->resolver();
        $granted = $resolver->effectivePermissions($this->getAuthIdentifier());

        return $resolver->matches($permission, $granted);
    }

    /** @return list<string> همهٔ مجوزهای مؤثر */
    public function permissions(): array
    {
        return $this->rbacGate()->resolver()->effectivePermissions($this->getAuthIdentifier());
    }

    /** @return list<string> کلید نقش‌ها */
    public function roleKeys(): array
    {
        return $this->rbacRoles()->rolesOf($this);
    }

    public function assignRole(string $roleKey, ?int $tenantId = null): void
    {
        $this->rbacRoles()->assign($this, $roleKey, $tenantId);
    }

    public function removeRole(string $roleKey, ?int $tenantId = null): void
    {
        $this->rbacRoles()->remove($this, $roleKey, $tenantId);
    }

    public function can(string $ability, mixed ...$arguments): bool
    {
        return $this->rbacGate()->forUser($this)->allows($ability, ...$arguments);
    }

    public function cannot(string $ability, mixed ...$arguments): bool
    {
        return !$this->can($ability, ...$arguments);
    }

    public function isSuperAdmin(): bool
    {
        $config = Facade::getFacadeApplication()->bound('config')
            ? Facade::getFacadeApplication()->make('config')->get('rbac.super_admin.role', 'super-admin')
            : 'super-admin';

        return $this->hasRole((string) $config);
    }
}
