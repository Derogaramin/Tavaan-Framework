<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * RoleReaderInterface — پورت خواندن نقش‌های کاربر (RBAC — بدون ارجاع به موتور).
 */

namespace Modules\Bpms\Domain\Repositories;

/**
 * RoleReaderInterface — نقش‌های یک کاربر برای تخصیص کار
 * (`assignee: role:x`) و inbox. آداپتر: DatabaseRoleReader (جداول RBAC).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
interface RoleReaderInterface
{
    /**
     * کلید نقش‌های کاربر (خالی اگر RBAC در دسترس نبود).
     *
     * @return list<string>
     */
    public function rolesOf(int $userId): array;

    /**
     * شناسهٔ کاربران دارای هر یک از نقش‌ها (اعلان/escalation — best-effort).
     *
     * @param list<string> $roles
     *
     * @return list<int>
     */
    public function usersOfRoles(array $roles): array;
}
