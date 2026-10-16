<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — FeatureFlag
 * config/features.php — پیکربندی پرچم‌های قابلیت (DOC-075 §6).
 */

return [
    // config|database
    'driver' => env_string('TVN_FEATURE_DRIVER', 'config'),

    // ttl کش تعاریف DB (ثانیه)
    'cache_ttl' => env_int('TVN_FEATURE_CACHE_TTL', 60),

    // 🔒 نسخهٔ کلید cache — با تغییر آن، همهٔ کش‌های تعاریف باطل می‌شوند
    'version' => 'v1',

    // جدول درایور database
    'table' => 'tvn_features',

    'features' => [
        // 'shop.gift-card' => ['enabled' => false, 'default' => false, 'rules' => []],
        // 'new.checkout' => ['enabled' => true, 'rules' => [['type' => 'percentage', 'value' => 10]]],
        // 'theme.color' => ['enabled' => true, 'default' => true, 'value' => 'blue'],
    ],
];
