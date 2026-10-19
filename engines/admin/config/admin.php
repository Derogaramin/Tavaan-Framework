<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Admin
 * config/admin.php — پیکربندی پیشخوان (DOC-078 §12).
 */

return [
    // segment اول مسیرهای پیشخوان: /admin/...
    'prefix' => env_string('TVN_ADMIN_PREFIX', 'admin'),

    // گارد احراز هویت (AuthenticateMiddleware) — null = گارد پیش‌فرض
    'guard' => env_string('TVN_ADMIN_GUARD', 'web'),

    // تعداد ردیف هر صفحه در فهرست‌ها (پیش‌فرضِ resource->perPage)
    'per_page' => env_int('TVN_ADMIN_PER_PAGE', 25),

    // میان‌بر resourceها در منو
    'menu' => [
        'dashboard_label' => 'داشبورد',
        'auto_resources' => true,
    ],
];
