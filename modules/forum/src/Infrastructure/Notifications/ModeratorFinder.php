<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ModeratorFinder — یافتن کاربران نقش‌های moderation (برای اعلان صف).
 */

namespace Modules\Forum\Infrastructure\Notifications;

use Tavaan\Core\Facade\Facades\DB;

/**
 * ModeratorFinder — کاربران دارای نقش‌های پیکربندی‌شده (پیش‌فرض
 * moderator/admin) از جداول RBAC (نام جدول‌ها از config('rbac.tables')).
 *
 * 🔒 ARCH-003: بدون ارجاع به کلاس موتور — فقط DB Facade + config.
 * best-effort: در نبود جداول RBAC فهرست خالی برمی‌گرداند.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ModeratorFinder
{
    /**
     * @param list<string> $roles کلید نقش‌ها (config forum.notifications.moderator_roles)
     */
    public function __construct(private readonly array $roles = ['moderator', 'admin'])
    {
    }

    /**
     * @return list<int> شناسهٔ کاربران
     */
    public function userIds(): array
    {
        if ($this->roles === []) {
            return [];
        }

        try {
            $tables = \function_exists('config') ? (array) config('rbac.tables', []) : [];
            $rolesTable = (string) ($tables['roles'] ?? 'tvn_roles');
            $roleUserTable = (string) ($tables['role_user'] ?? 'tvn_role_user');

            $roleRows = DB::table($rolesTable)
                ->select('id')
                ->whereIn('key', $this->roles)
                ->get();
            $roleIds = \array_map(static fn (array $row): int => (int) $row['id'], $roleRows);
            if ($roleIds === []) {
                return [];
            }

            $userRows = DB::table($roleUserTable)
                ->select('user_id')
                ->whereIn('role_id', $roleIds)
                ->get();

            $ids = [];
            foreach ($userRows as $row) {
                $ids[] = (int) $row['user_id'];
            }

            return \array_values(\array_unique($ids));
        } catch (\Throwable) {
            return [];
        }
    }
}
