<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — RBAC
 * RoleManager — CRUD نقش/مجوز + assign/remove + سلسله‌مراتب (DOC-057 §5).
 *
 * 🔒 assignRole با رویداد rbac.role.assigned (برای Audit اجباری — DOC-058 §5).
 * 🔒 پس از هر تغییر، کش مجوزهای کاربر invalidate می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
final class RoleManager
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly PermissionResolver $resolver,
        private readonly array $config = [],
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    private function table(string $key): string
    {
        return (string) ($this->config['tables'][$key] ?? 'tvn_' . $key);
    }

    // ─── نقش‌ها ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> رکورد نقش */
    public function create(string $key, string $name, ?string $parentKey = null, bool $isSystem = false, string $scope = 'global'): array
    {
        $parentId = null;
        if ($parentKey !== null) {
            $parent = $this->findRole($parentKey);
            $parentId = $parent === null ? null : (int) $parent['id'];

            // 🔒 محافظ عمق سلسله‌مراتب
            if ($parentId !== null && $this->depthOf($parentId) >= (int) ($this->config['hierarchy']['max_depth'] ?? 5)) {
                throw new \InvalidArgumentException('Role hierarchy max depth exceeded.');
            }
        }

        $existing = $this->findRole($key);
        $now = Clock::utcNow()->format('Y-m-d H:i:s');

        if ($existing !== null) {
            $this->db->table($this->table('roles'))->where('id', $existing['id'])->update([
                'name' => $name, 'parent_id' => $parentId, 'scope' => $scope,
            ]);

            /** @var array<string, mixed> $existing */
            return $existing;
        }

        $id = $this->db->table($this->table('roles'))->insertGetId([
            'key' => $key,
            'name' => $name,
            'parent_id' => $parentId,
            'is_system' => $isSystem ? 1 : 0,
            'scope' => $scope,
            'created_at' => $now,
        ]);

        return ['id' => $id, 'key' => $key, 'name' => $name, 'parent_id' => $parentId];
    }

    /** @return array<string, mixed>|null */
    public function findRole(string $key): ?array
    {
        return $this->db->table($this->table('roles'))->where('key', $key)->first();
    }

    private function depthOf(int $roleId): int
    {
        $depth = 0;
        $current = $this->db->table($this->table('roles'))->where('id', $roleId)->first();
        $guard = 0;

        while ($current !== null && $current['parent_id'] !== null && $guard < 32) {
            $depth++;
            $current = $this->db->table($this->table('roles'))->where('id', (int) $current['parent_id'])->first();
            $guard++;
        }

        return $depth;
    }

    // ─── مجوزها ────────────────────────────────────────────────────────────

    /** ثبت/به‌روزرسانی مجوز. @return array<string, mixed> */
    public function createPermission(string $key, string $module = '', string $titleFa = '', string $titleEn = ''): array
    {
        $existing = $this->db->table($this->table('permissions'))->where('key', $key)->first();
        $now = Clock::utcNow()->format('Y-m-d H:i:s');

        if ($existing !== null) {
            /** @var array<string, mixed> $existing */
            return $existing;
        }

        $id = $this->db->table($this->table('permissions'))->insertGetId([
            'key' => $key,
            'module' => $module !== '' ? $module : (\explode('.', $key)[0] ?? ''),
            'title_fa' => $titleFa,
            'title_en' => $titleEn,
            'created_at' => $now,
        ]);

        return ['id' => $id, 'key' => $key];
    }

    /** اختصاص مجوز به نقش. */
    public function grantPermissionToRole(string $roleKey, string $permissionKey): void
    {
        $role = $this->findRole($roleKey);
        $permission = $this->db->table($this->table('permissions'))->where('key', $permissionKey)->first();

        if ($role === null || $permission === null) {
            throw new \InvalidArgumentException(\sprintf('Role [%s] or permission [%s] not found.', $roleKey, $permissionKey));
        }

        $this->db->table($this->table('permission_role'))->updateOrInsert(
            ['permission_id' => (int) $permission['id'], 'role_id' => (int) $role['id']],
            [],
        );
    }

    public function revokePermissionFromRole(string $roleKey, string $permissionKey): void
    {
        $role = $this->findRole($roleKey);
        $permission = $this->db->table($this->table('permissions'))->where('key', $permissionKey)->first();

        if ($role === null || $permission === null) {
            return;
        }

        $this->db->table($this->table('permission_role'))
            ->where('permission_id', (int) $permission['id'])
            ->where('role_id', (int) $role['id'])
            ->delete();
    }

    // ─── assign / remove ──────────────────────────────────────────────────

    /**
     * @param int|string|null $tenantId نقش tenant-scoped
     * @param \DateTimeInterface|null $expiresAt 🔒 نقش موقت
     */
    public function assign(Authenticatable|int|string $user, string $roleKey, ?int $tenantId = null, ?\DateTimeInterface $expiresAt = null, ?int $grantedBy = null): void
    {
        $userId = $this->userId($user);
        $role = $this->findRole($roleKey);
        if ($role === null) {
            throw new \InvalidArgumentException(\sprintf('Role [%s] not found.', $roleKey));
        }

        $maxDays = (int) ($this->config['delegation']['max_duration_days'] ?? 90);
        if ($expiresAt !== null && $expiresAt->getTimestamp() - Clock::timestamp() > $maxDays * 86400) {
            throw new \InvalidArgumentException(\sprintf('Role assignment cannot exceed %d days.', $maxDays));
        }

        $this->db->table($this->table('role_user'))->updateOrInsert(
            ['role_id' => (int) $role['id'], 'user_id' => (string) $userId, 'tenant_id' => $tenantId],
            [
                'expires_at' => $expiresAt === null ? null : $this->utcFormat($expiresAt),
                'granted_by' => $grantedBy,
            ],
        );

        $this->resolver->invalidate($userId, $tenantId);
        $this->events?->dispatch('rbac.role.assigned', [
            'user' => (string) $userId, 'role' => $roleKey, 'tenant' => $tenantId,
        ]);
    }

    public function remove(Authenticatable|int|string $user, string $roleKey, ?int $tenantId = null): void
    {
        $userId = $this->userId($user);
        $role = $this->findRole($roleKey);
        if ($role === null) {
            return;
        }

        $query = $this->db->table($this->table('role_user'))
            ->where('role_id', (int) $role['id'])
            ->where('user_id', (string) $userId);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $query->delete();

        $this->resolver->invalidate($userId, $tenantId);
        $this->events?->dispatch('rbac.role.removed', ['user' => (string) $userId, 'role' => $roleKey]);
    }

    /** @return list<string> کلید نقش‌های فعال کاربر */
    public function rolesOf(Authenticatable|int|string $user, ?int $tenantId = null): array
    {
        $userId = $this->userId($user);
        $now = Clock::utcNow()->format('Y-m-d H:i:s');

        $query = $this->db->table($this->table('role_user') . ' as ru')
            ->join($this->table('roles') . ' as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', (string) $userId)
            ->where(static fn ($q) => $q->whereNull('ru.expires_at')->orWhere('ru.expires_at', '>', $now));

        if ($tenantId !== null) {
            $query->where(static fn ($q) => $q->whereNull('ru.tenant_id')->orWhere('ru.tenant_id', $tenantId));
        }

        return \array_map(static fn (array $row): string => (string) $row['key'], $query->get());
    }

    public function hasRole(Authenticatable|int|string $user, string $roleKey, ?int $tenantId = null): bool
    {
        return \in_array($roleKey, $this->rolesOf($user, $tenantId), true);
    }

    /** @param list<string> $roleKeys */
    public function hasAnyRole(Authenticatable|int|string $user, array $roleKeys, ?int $tenantId = null): bool
    {
        $roles = $this->rolesOf($user, $tenantId);
        foreach ($roleKeys as $key) {
            if (\in_array($key, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    // ─── delegation ───────────────────────────────────────────────────────

    /**
     * تفویض اختیار موقت (DOC-057 §2.1 tvn_delegations).
     *
     * @param list<string>|'*' $permissions
     */
    public function delegate(int|string $fromUserId, int|string $toUserId, array|string $permissions, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt, string $reason = ''): int|string
    {
        if (!(bool) ($this->config['delegation']['enabled'] ?? true)) {
            throw new \LogicException('Delegation is disabled.');
        }

        $maxDays = (int) ($this->config['delegation']['max_duration_days'] ?? 90);
        if ($endsAt->getTimestamp() - $startsAt->getTimestamp() > $maxDays * 86400) {
            throw new \InvalidArgumentException(\sprintf('Delegation cannot exceed %d days.', $maxDays));
        }

        $id = $this->db->table($this->table('delegations'))->insertGetId([
            'from_user_id' => (string) $fromUserId,
            'to_user_id' => (string) $toUserId,
            'permissions' => (string) \json_encode(\is_array($permissions) ? $permissions : [$permissions], JSON_THROW_ON_ERROR),
            'starts_at' => $this->utcFormat($startsAt),
            'ends_at' => $this->utcFormat($endsAt),
            'reason' => $reason,
            'created_at' => Clock::utcNow()->format('Y-m-d H:i:s'),
        ]);

        $this->resolver->invalidate($toUserId);
        $this->events?->dispatch('rbac.delegation.created', ['from' => (string) $fromUserId, 'to' => (string) $toUserId]);

        return $id;
    }

    public function revokeDelegation(int|string $delegationId): void
    {
        $record = $this->db->table($this->table('delegations'))->where('id', $delegationId)->first();
        if ($record === null) {
            return;
        }

        $this->db->table($this->table('delegations'))->where('id', $delegationId)->delete();
        $this->resolver->invalidate((string) $record['to_user_id']);
    }

    private function userId(Authenticatable|int|string $user): int|string
    {
        return $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;
    }

    /** 🔒 یکسان‌سازی به UTC (مقایسهٔ رشته‌ای expires_at با Clock::utcNow). */
    private function utcFormat(\DateTimeInterface $dateTime): string
    {
        return \DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }
}
