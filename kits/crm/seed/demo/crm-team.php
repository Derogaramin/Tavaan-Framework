<?php

declare(strict_types=1);

/*
 * دادهٔ نمونهٔ سطح-کیت CRM (پس از seed ماژول‌ها — مخاطبان/سرنخ‌ها/تیکت‌ها
 * آنجا ساخته می‌شوند). قرارداد: callable() — idempotent.
 *
 * نقش‌های CRM را به کاربران دمو (core-user) اختصاص می‌دهد.
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    $assignments = [
        'admin@blog.test' => ['crm-admin', 'admin'],
        'author@blog.test' => ['sales-manager'],
        'editor@blog.test' => ['support-manager'],
        'reader@blog.test' => ['sales-agent'],
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
