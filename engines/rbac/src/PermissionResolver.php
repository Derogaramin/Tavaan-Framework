<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — RBAC
 * PermissionResolver — محاسبهٔ مجوزهای مؤثر کاربر (DOC-057 §6.1).
 *
 * ```
 * effectivePermissions(user, tenant):
 *   roles = user.roles(tenant)
 *   for role: نقش‌های والد تا max_depth → permissions
 *   + delegationهای فعال
 *   → کش (tvn:rbac:user:{id}:perms) با invalidate در تغییر نقش
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
final class PermissionResolver
{
    /** @var array<string, list<string>> کش درون‌فرایندی (per-request) */
    private array $memo = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ?CacheRepository $cache = null,
        private readonly array $config = [],
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    private function table(string $key): string
    {
        return (string) ($this->config['tables'][$key] ?? 'tvn_' . $key);
    }

    /**
     * همهٔ مجوزهای مؤثر کاربر (نقش‌ها + سلسله‌مراتب + delegation).
     *
     * @return list<string>
     */
    public function effectivePermissions(int|string $userId, ?int $tenantId = null): array
    {
        $cacheKey = $this->cacheKey($userId, $tenantId);

        if (isset($this->memo[$cacheKey])) {
            return $this->memo[$cacheKey];
        }

        if ($this->cacheEnabled() && $this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if (\is_array($cached)) {
                /** @var list<string> $cached */
                return $this->memo[$cacheKey] = $cached;
            }
        }

        $permissions = $this->resolve($userId, $tenantId);

        if ($this->cacheEnabled() && $this->cache !== null) {
            $this->cache->put($cacheKey, $permissions, (int) ($this->config['cache']['ttl'] ?? 3600));
        }

        return $this->memo[$cacheKey] = $permissions;
    }

    /**
     * @return list<string>
     */
    private function resolve(int|string $userId, ?int $tenantId): array
    {
        $now = Clock::utcNow()->format('Y-m-d H:i:s');
        $permissions = [];

        // 1) نقش‌های کاربر (با tenant و انقضا)
        $roleQuery = $this->db->table($this->table('role_user') . ' as ru')
            ->join($this->table('roles') . ' as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', (string) $userId)
            ->where(static fn ($q) => $q->whereNull('ru.expires_at')->orWhere('ru.expires_at', '>', $now));

        if ($tenantId !== null) {
            $roleQuery->where(static fn ($q) => $q->whereNull('ru.tenant_id')->orWhere('ru.tenant_id', $tenantId));
        }

        $roleRows = $roleQuery->get();

        // 2) سلسله‌مراتب: هر نقش + اجدادش
        $roleIds = [];
        foreach ($roleRows as $row) {
            $roleIds[] = (int) $row['role_id'];
        }
        $roleIds = $this->withAncestors($roleIds);

        // 3) مجوزهای نقش‌ها
        if ($roleIds !== []) {
            $permRows = $this->db->table($this->table('permission_role') . ' as pr')
                ->join($this->table('permissions') . ' as p', 'p.id', '=', 'pr.permission_id')
                ->whereIn('pr.role_id', $roleIds)
                ->get();

            foreach ($permRows as $row) {
                $permissions[] = (string) $row['key'];
            }
        }

        // 4) delegationهای فعال
        if ((bool) ($this->config['delegation']['enabled'] ?? true)) {
            $delegations = $this->db->table($this->table('delegations'))
                ->where('to_user_id', (string) $userId)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now)
                ->get();

            foreach ($delegations as $delegation) {
                $decoded = \json_decode((string) $delegation['permissions'], true);
                if (\is_array($decoded)) {
                    foreach ($decoded as $permission) {
                        $permissions[] = (string) $permission;
                    }
                }
            }
        }

        return \array_values(\array_unique($permissions));
    }

    /**
     * افزودن اجداد نقش‌ها (سلسله‌مراتب — با سقف max_depth و محافظ چرخه).
     *
     * @param list<int> $roleIds
     *
     * @return list<int>
     */
    private function withAncestors(array $roleIds): array
    {
        if (!$this->hierarchyEnabled() || $roleIds === []) {
            return $roleIds;
        }

        $maxDepth = (int) ($this->config['hierarchy']['max_depth'] ?? 5);

        // نقش‌ها یک‌بار خوانده شوند (جلوگیری از N+1)
        $all = $this->db->table($this->table('roles'))->get(['id', 'parent_id']);
        $parentOf = [];
        foreach ($all as $row) {
            $parentOf[(int) $row['id']] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        }

        $expanded = [];
        foreach ($roleIds as $roleId) {
            $current = $roleId;
            $depth = 0;
            $visited = [];
            while ($current !== null && $depth <= $maxDepth && !isset($visited[$current])) {
                $visited[$current] = true;
                $expanded[] = $current;
                $current = $parentOf[$current] ?? null;
                $depth++;
            }
        }

        return \array_values(\array_unique($expanded));
    }

    /**
     * بررسی مجوز با wildcard (shop.* → shop.order.refund).
     *
     * @param list<string> $granted
     */
    public function matches(string $ability, array $granted): bool
    {
        if (\in_array($ability, $granted, true)) {
            return true;
        }

        if (!(bool) ($this->config['wildcard']['enabled'] ?? true)) {
            return false;
        }

        foreach ($granted as $permission) {
            if ($this->wildcardMatches($permission, $ability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * الگوی مجوز (مثل shop.*) روی ability مطابقت دهد؟
     *
     * 🔒 '*' تنها (همه‌چیز) فقط در Gate برای super-admin بررسی می‌شود.
     */
    private function wildcardMatches(string $pattern, string $ability): bool
    {
        if ($pattern === $ability) {
            return true;
        }
        if (!\str_contains($pattern, '*') || $pattern === '*') {
            return false;
        }

        $regex = '/^' . \str_replace('\*', '.*', \preg_quote($pattern, '/')) . '$/';

        return \preg_match($regex, $ability) === 1;
    }

    public function invalidate(int|string $userId, ?int $tenantId = null): void
    {
        unset($this->memo[$this->cacheKey($userId, $tenantId)]);

        if ($this->cache !== null) {
            // همهٔ tenantهای کاربر
            $this->cache->forget($this->cacheKey($userId, null));
            if ($tenantId !== null) {
                $this->cache->forget($this->cacheKey($userId, $tenantId));
            }
        }

        $this->events?->dispatch('rbac.permissions.invalidated', ['user' => (string) $userId]);
    }

    public function flushMemo(): void
    {
        $this->memo = [];
    }

    private function cacheKey(int|string $userId, ?int $tenantId): string
    {
        $prefix = (string) ($this->config['cache']['prefix'] ?? 'tvn:rbac:');

        return $prefix . 'user:' . $userId . ':perms' . ($tenantId !== null ? ':t' . $tenantId : '');
    }

    private function cacheEnabled(): bool
    {
        return (bool) ($this->config['cache']['enabled'] ?? true);
    }

    private function hierarchyEnabled(): bool
    {
        return (bool) ($this->config['hierarchy']['enabled'] ?? true);
    }
}
