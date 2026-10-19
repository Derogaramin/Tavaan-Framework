<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Upgrade
 * config/upgrade.php — پیکربندی جریان ارتقا (DOC-081E).
 */

return [
    // ریشهٔ patchهای ارتقا: upgrades/<version>/patch_*.php + config.php
    'path' => base_path('upgrades'),

    // 🔒 pre-check (DOC-081E §2 گام ۱)
    'precheck' => [
        'php_min' => '8.2.0',
        'extensions' => ['json', 'mbstring', 'pdo', 'openssl'],
        'min_free_mb' => 100,
    ],

    // backup اجباری پیش از ارتقا (§8) — فقط dev/CI با --no-backup
    'require_backup' => env_bool('TVN_UPGRADE_BACKUP', true),

    // اجرای schema migrationها در هر گام نسخه
    'schema_migrations' => true,

    // maintenance mode (§2 گام ۳)
    'maintenance' => [
        'enabled' => true,
        'message' => 'سیستم در حال ارتقا است… / Upgrading…',
    ],

    // مسیر فایل down (هم‌مسیر با MaintenanceModeMiddleware)
    'down_file' => storage_path('framework/down'),
];
