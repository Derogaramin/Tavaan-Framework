<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Plugin
 * config/plugins.php — پیکربندی سیستم افزونهٔ ثالث (DOC-079 §8).
 */

return [
    'path' => base_path('plugins'),

    // master switch — با false هیچ افزونه‌ای load/نصب نمی‌شود
    'enabled' => env_bool('TVN_PLUGINS', true),

    // 🔒 AC-PLG-09: بدون امضای Ed25519 معتبر، نصب رد می‌شود
    'require_signature' => env_bool('TVN_PLUGIN_SIGNATURE', true),

    // کلیدهای عمومی فروشنده‌های مورد اعتماد: 'ACME' => 'ed25519:base64:…'
    'trusted_vendors' => [],

    // معافیت صفر‌وابستگی: vendor اختصاصی هر افزونه مجاز است (DOC-079 §1)
    'allow_vendor' => true,

    // hookهای اعلام‌شدهٔ سطح اپ (علاوه بر module.json ماژول‌ها)
    'allowed_hooks' => [],

    // سیاست sandbox (DOC-079 §8/§9)
    'sandbox' => [
        'database' => false,          // دسترسی مستقیم DB ممنوع — فقط Contract
        'shell' => false,             // exec/system/… ممنوع
        'network_allowlist' => true,  // فقط دامنه‌های capabilities.network
    ],

    'lock_file' => base_path('plugins/plugins.lock'),

    // محافظت DoS برای hookها (§9) — enforcement همکاری‌کننده
    'hook_timeout_ms' => env_int('TVN_PLUGIN_HOOK_TIMEOUT', 5000),
    'hook_memory_limit' => null,      // bytes؛ null = بدون محدودیت
];
