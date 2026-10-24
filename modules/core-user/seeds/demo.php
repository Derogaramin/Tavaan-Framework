<?php

declare(strict_types=1);

/*
 * Seeder دمو — ماژول core-user (توسط `tvn kit:install --with-demo` اجرا می‌شود).
 *
 * قرارداد: فایل callable(ConnectionInterface) برمی‌گرداند (بدون autoload).
 * 🔒 Idempotent — اجرای مجدد رکورد تکراری نمی‌سازد.
 */

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Hash;

return static function (ConnectionInterface $db): void {
    $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

    $users = [
        ['name' => 'مدیر سایت', 'email' => 'admin@blog.test', 'status' => 'active'],
        ['name' => 'ویراستار', 'email' => 'editor@blog.test', 'status' => 'active'],
        ['name' => 'نویسنده', 'email' => 'author@blog.test', 'status' => 'active'],
        ['name' => 'خواننده', 'email' => 'reader@blog.test', 'status' => 'active'],
    ];

    $password = Hash::make('password');
    foreach ($users as $user) {
        $exists = DB::table('users')->where('email', $user['email'])->exists();
        if (!$exists) {
            DB::table('users')->insert([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $password,
                'status' => $user['status'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // نقش‌ها — اگر جداول RBAC مهاجرت شده باشند (kit:install → rbac sync)
    $roleTableExists = false;
    try {
        $roleTableExists = DB::table('tvn_roles')->count() >= 0;
    } catch (Throwable) {
        $roleTableExists = false;
    }

    if ($roleTableExists && $db instanceof ConnectionInterface) {
        $roleMap = [
            'admin@blog.test' => ['admin', 'super-admin'],
            'editor@blog.test' => ['editor'],
            'author@blog.test' => ['author'],
            'reader@blog.test' => ['reader'],
        ];

        /** @var object|null $roles */
        $roles = function_exists('app') && app()->bound('rbac.roles') ? app()->make('rbac.roles') : null;
        if ($roles !== null && method_exists($roles, 'hasRole')) {
            foreach ($roleMap as $email => $roleKeys) {
                $row = DB::table('users')->where('email', $email)->first();
                if ($row === null) {
                    continue;
                }
                foreach ($roleKeys as $roleKey) {
                    $roleRow = DB::table('tvn_roles')->where('key', $roleKey)->first();
                    if ($roleRow === null) {
                        continue;
                    }
                    if (!$roles->hasRole((int) $row['id'], $roleKey)) {
                        $roles->assign((int) $row['id'], $roleKey);
                    }
                }
            }
        }
    }
};
