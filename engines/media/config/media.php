<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Media
 * config/media.php — پیکربندی آپلود و پردازش رسانه (DOC-074).
 */

return [
    // disk پیش‌فرض برای آپلودها (🔒 باید خارج از DocumentRoot اجرایی باشد)
    'disk' => env_string('TVN_MEDIA_DISK', 'public'),

    // پوشهٔ پیش‌فرض داخل disk
    'folder' => env_string('TVN_MEDIA_FOLDER', 'media'),

    // جدول کتابخانهٔ رسانه
    'table' => 'tvn_media',

    // سقف حجم (بایت) — پیش‌فرض ۱۰MB
    'max_size' => env_int('TVN_MEDIA_MAX_SIZE', 10485760),

    // 🔒 allowlist پسوند (هرچه خارج از این فهرست است رد می‌شود — مثل php/exe/sh)
    'allowed_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp',
        'pdf', 'zip',
        'mp3', 'mp4',
        'txt', 'csv',
    ],

    // 🔒 allowlist MIME واقعی (magic bytes)
    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp',
        'application/pdf', 'application/zip',
        'audio/mpeg', 'video/mp4',
        'text/plain', 'text/csv',
    ],

    'image' => [
        // auto → imagick → gd → none (بدون پردازش، فقط ذخیره)
        'driver' => env_string('TVN_MEDIA_DRIVER', 'auto'),

        // 🔒 Image Bomb: سقف ابعاد (DOC-074 §7)
        'max_dimension' => env_int('TVN_MEDIA_MAX_DIMENSION', 20000),

        // re-encode تصویر هنگام آپلود — 🔒 polyglot/EXIF را پاک می‌کند (نیازمند gd/imagick)
        'reencode' => env_bool('TVN_MEDIA_REENCODE', true),

        // 🔒 حذف EXIF (جلوگیری از نشت GPS)
        'strip_exif' => env_bool('TVN_MEDIA_STRIP_EXIF', true),

        'quality' => env_int('TVN_MEDIA_QUALITY', 85),
    ],

    // 🔒 اسکن بایتی کد جاسازی‌شده (<?php) وقتی re-encode ممکن نیست
    'paranoid_scan' => env_bool('TVN_MEDIA_PARANOID', true),

    // حذف تکراری‌ها با hash (DOC-074 §6: idx_hash)
    'dedup' => env_bool('TVN_MEDIA_DEDUP', true),

    // پیش‌تنظیم‌های variants (DOC-074 §3)
    'variants' => [
        'thumb' => ['w' => 150, 'h' => 150, 'fit' => 'crop'],
        'medium' => ['w' => 600],
        'large' => ['w' => 1200],
        'webp' => ['format' => 'webp', 'quality' => 80],
    ],
];
