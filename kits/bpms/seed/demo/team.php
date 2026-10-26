<?php

declare(strict_types=1);

/*
 * دادهٔ نمونهٔ سطح-کیت BPMS (پس از seed ماژول — تعاریف/نمونه‌ها آنجا
 * ساخته می‌شوند). قرارداد: callable() — idempotent.
 *
 * نقش‌های BPMS را به کاربران دمو (core-user) اختصاص می‌دهد:
 * admin=process-admin، editor=process-designer+manager،
 * author=task-assignee، reader=auditor.
 * همچنین نقش‌های کسب‌وکاری سناریوهای دمو (manager/finance/warehouse)
 * به کاربران مناسب می‌رسد تا کارهای role-based در inbox دیده شوند.
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    $assignments = [
        'admin@blog.test' => ['process-admin', 'admin'],
        'editor@blog.test' => ['process-designer', 'manager'],
        'author@blog.test' => ['task-assignee', 'finance'],
        'reader@blog.test' => ['auditor', 'monitor'],
    ];

    foreach ($assignments as $email => $roleKeys) {
        $user = DB::table('users')->where('email', $email)->first();
        if ($user === null) {
            continue;
        }

        foreach ($roleKeys as $roleKey) {
            $role = DB::table('tvn_roles')->where('key', $roleKey)->first();
            if ($role === null) {
                continue;
            }

            $exists = DB::table('tvn_role_user')
                ->where('user_id', $user['id'])
                ->where('role_id', $role['id'])
                ->exists();
            if (!$exists) {
                DB::table('tvn_role_user')->insert([
                    'user_id' => $user['id'],
                    'role_id' => $role['id'],
                ]);
            }
        }
    }
};
