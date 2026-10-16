<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Schedule
 * config/schedule.php — پیکربندی زمان‌بندی (DOC-062 §5).
 */

return [
    'timezone' => env_string('TVN_SCHEDULE_TZ', 'Asia/Tehran'),

    // فروشگاه cache برای mutex/onOneServer — '' = پیش‌فرض
    // 🔒 در استقرار چندسروره باید store اشتراکی (redis/database) باشد
    'cache_store' => env_string('TVN_SCHEDULE_CACHE', ''),

    'default_queue' => env_string('TVN_SCHEDULE_QUEUE', 'schedule'),

    // اجرای وظایف حتی در maintenance mode
    'run_in_maintenance' => env_bool('TVN_SCHEDULE_RUN_IN_MAINTENANCE', false),
];
