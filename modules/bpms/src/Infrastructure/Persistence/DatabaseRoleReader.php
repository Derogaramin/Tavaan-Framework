<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DatabaseRoleReader — خواندن نقش‌ها از جداول RBAC (بدون ارجاع به موتور).
 */

namespace Modules\Bpms\Infrastructure\Persistence;

use Modules\Bpms\Domain\Repositories\RoleReaderInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseRoleReader — ARCH-003: ماژول نمی‌تواند کلاس موتور rbac را
 * import کند؛ نقش‌ها مستقیم از `tvn_role_user`/`tvn_roles` خوانده
 * می‌شوند (الگوی CrmNotifier::usersOfRoles — best-effort).
 *
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class DatabaseRoleReader implements RoleReaderInterface
{
    public function rolesOf(int $userId): array
    {
        try {
            $rows = DB::table('tvn_role_user')
                ->select('tvn_roles.key')
                ->join('tvn_roles', 'tvn_roles.id', '=', 'tvn_role_user.role_id')
                ->where('tvn_role_user.user_id', $userId)
                ->get();
        } catch (\Throwable) {
            return [];   // RBAC نصب نیست — fail-safe
        }

        $keys = [];
        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return \array_values(\array_unique($keys));
    }

    public function usersOfRoles(array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        try {
            $roleRows = DB::table('tvn_roles')->whereIn('key', $roles)->get();
            $roleIds = [];
            foreach ($roleRows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $roleIds[] = $id;
                }
            }
            if ($roleIds === []) {
                return [];
            }

            $assignmentRows = DB::table('tvn_role_user')->whereIn('role_id', $roleIds)->get();
        } catch (\Throwable) {
            return [];
        }

        $userIds = [];
        foreach ($assignmentRows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = true;
            }
        }

        return \array_keys($userIds);
    }
}
