<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;

/**
 * تَوان | Tavaan — RBAC
 * PermissionSync — همگام‌سازی مجوزها از manifest ماژول/موتور (DOC-057 §7).
 *
 * در `tvn rbac:sync` و نصب ماژول اجرا می‌شود؛ مجوزهای حذف‌شده با
 * رویداد (→ Audit) ثبت می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
final class PermissionSync
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly RoleManager $roles,
        private readonly array $config = [],
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    private function permissionsTable(): string
    {
        return (string) ($this->config['tables']['permissions'] ?? 'tvn_permissions');
    }

    /**
     * همگام‌سازی یک مجموعه مجوز اعلام‌شده برای یک ماژول.
     *
     * @param list<string> $declared کلیدهای مجوز (module.resource.action)
     *
     * @return array{added: list<string>, removed: list<string>}
     */
    public function sync(string $moduleKey, array $declared): array
    {
        $existingRows = $this->db->table($this->permissionsTable())
            ->where('module', $moduleKey)
            ->pluck('key');
        $existing = \array_map('strval', $existingRows);

        $toAdd = \array_values(\array_diff($declared, $existing));
        $toRemove = \array_values(\array_diff($existing, $declared));

        foreach ($toAdd as $key) {
            $this->roles->createPermission($key, $moduleKey);
        }

        if ($toRemove !== []) {
            // 🔒 حذف با ثبت رویداد (Audit) — پیوندهای نقش هم پاک شود
            $ids = $this->db->table($this->permissionsTable())
                ->whereIn('key', $toRemove)
                ->pluck('id');

            $this->db->table($this->config['tables']['permission_role'] ?? 'tvn_permission_role')
                ->whereIn('permission_id', $ids)
                ->delete();

            $this->db->table($this->permissionsTable())
                ->whereIn('key', $toRemove)
                ->delete();
        }

        $this->events?->dispatch('rbac.permissions.synced', [
            'module' => $moduleKey,
            'added' => $toAdd,
            'removed' => $toRemove,
        ]);

        return ['added' => $toAdd, 'removed' => $toRemove];
    }

    /**
     * همگام‌سازی از همهٔ manifestهای کشف‌شده (module.json → permissions).
     *
     * @return array<string, array{added: list<string>, removed: list<string>}>
     */
    public function syncFromModules(string $basePath): array
    {
        $results = [];

        foreach (\glob($basePath . '/modules/*/module.json') ?: [] as $manifestPath) {
            $manifest = $this->readManifest($manifestPath);
            if ($manifest === null) {
                continue;
            }
            $results[$manifest['id']] = $this->sync($manifest['id'], $manifest['permissions']);
        }

        return $results;
    }

    /** @return array{id: string, permissions: list<string>}|null */
    private function readManifest(string $path): ?array
    {
        $raw = \file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        try {
            $decoded = \json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($decoded) || !isset($decoded['id'])) {
            return null;
        }

        $permissions = [];
        foreach ((array) ($decoded['permissions'] ?? []) as $permission) {
            if (\is_string($permission) && $permission !== '') {
                $permissions[] = $permission;
            }
        }

        return ['id' => (string) $decoded['id'], 'permissions' => $permissions];
    }
}
