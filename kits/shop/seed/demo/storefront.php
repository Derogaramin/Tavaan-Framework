<?php

declare(strict_types=1);

/*
 * دادهٔ نمونهٔ سطح-کیت فروشگاه (پس از seed ماژول‌ها — محصولات/کوپن‌ها آنجا ساخته می‌شوند).
 * قرارداد: callable() — idempotent.
 *
 * ۱) نقش‌های فروشگاه را به کاربران دمو اختصاص می‌دهد
 * ۲) کوپن خوش‌آمد WELCOME1405 می‌سازد
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    $fmt = 'Y-m-d H:i:s.v';

    // ── ۱) انتصاب نقش به کاربران دمو (core-user demo) ─────────────────────
    $assignments = [
        'admin@blog.test' => 'admin',
        'editor@blog.test' => 'shop-manager',
        'author@blog.test' => 'shop-operator',
        'reader@blog.test' => 'customer',
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

    // ── ۲) کوپن خوش‌آمد کیت ───────────────────────────────────────────────
    if (!DB::table('shop_coupons')->where('code', 'WELCOME1405')->exists()) {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        DB::table('shop_coupons')->insert([
            'code' => 'WELCOME1405',
            'type' => 'percent',
            'value' => 15,
            'max_discount' => 20_000_000,
            'min_cart' => 500_000,
            'usage_limit' => 1000,
            'used_count' => 0,
            'expires_at' => $now->modify('+120 days')->format($fmt),
            'is_active' => 1,
            'created_at' => $now->format($fmt),
            'updated_at' => null,
        ]);
    }
};
