<?php

declare(strict_types=1);

/*
 * دادهٔ نمونهٔ سطح-کیت انجمن (پس از seed ماژول‌ها — تالارها/موضوعات آنجا
 * ساخته می‌شوند). قرارداد: callable() — idempotent.
 *
 * نقش‌های انجمن را به کاربران دمو (core-user) اختصاص می‌دهد.
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    $assignments = [
        'admin@blog.test' => 'admin',
        'editor@blog.test' => 'moderator',
        'author@blog.test' => 'member',
        'reader@blog.test' => 'member',
    ];

    foreach ($assignments as $email => $roleKey) {
        $user = DB::table('users')->where('email', $email)->first();
        $role = DB::table('tvn_roles')->where('key', $roleKey)->first();
        if ($user === null || $role === null) {
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
};
