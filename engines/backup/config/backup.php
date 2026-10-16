<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Backup
 * config/backup.php — پیکربندی پشتیبان‌گیری (DOC-068 §7).
 */

return [
    // disk مقصد آرشیوها (در config/filesystem.php تعریف می‌شود)
    'disk' => env_string('TVN_BACKUP_DISK', 'backups'),

    'schedule' => [
        'frequency' => 'daily',
        'time' => '03:00',
    ],

    // سیاست نگهداری (DOC-068 §7)
    'retention' => [
        'daily' => env_int('TVN_BACKUP_KEEP_DAILY', 7),
        'weekly' => env_int('TVN_BACKUP_KEEP_WEEKLY', 4),
        'monthly' => env_int('TVN_BACKUP_KEEP_MONTHLY', 12),
    ],

    // 🔒 رمزنگاری با کلید **جدا از APP_KEY** (DOC-068 §5/§9)
    'encrypt' => env_bool('TVN_BACKUP_ENCRYPT', true),
    'key' => env_string('TVN_BACKUP_KEY', ''),

    // مسیرهای شامل (base-relative یا مطلق)
    'include_files' => ['storage/app', 'config', '.env'],

    // مسیرهای حذف‌شده
    'exclude' => ['storage/cache', 'storage/logs', 'storage/temp', 'storage/queue'],

    // verify پس از هر پشتیبان
    'verify_after' => env_bool('TVN_BACKUP_VERIFY', true),

    'notify_on_failure' => env_bool('TVN_BACKUP_NOTIFY', true),
];
