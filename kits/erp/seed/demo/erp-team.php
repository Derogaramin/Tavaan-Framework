<?php

declare(strict_types=1);

/*
 * دادهٔ نمونهٔ سطح-کیت ERP (پس از seed ماژول — chart of accounts/انبار/کالا/
 * پرسنل آنجا ساخته می‌شوند). قرارداد: callable() — idempotent.
 *
 * نقش‌های ERP را به کاربران دمو (core-user) اختصاص می‌دهد:
 * admin=erp-admin، author=procurement-officer+sales-officer،
 * editor=finance-manager، reader=auditor.
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    $assignments = [
        'admin@blog.test' => ['erp-admin', 'admin'],
        'author@blog.test' => ['procurement-officer', 'sales-officer'],
        'editor@blog.test' => ['finance-manager', 'accountant'],
        'reader@blog.test' => ['auditor'],
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
