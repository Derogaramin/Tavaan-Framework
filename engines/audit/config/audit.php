<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Audit
 * config/audit.php — پیکربندی ممیزی (DOC-058 §6).
 */

return [
    'enabled' => env_bool('TVN_AUDIT_ENABLED', true),

    'table' => 'tvn_audit_logs',

    // 🔒 کلید HMAC زنجیره — جدا از APP_KEY (rotation متفاوت).
    // خالی → HKDF از APP_KEY با info اختصاصی (fallback).
    'key' => env_string('TVN_AUDIT_KEY', ''),

    'queue' => env_bool('TVN_AUDIT_QUEUE', false),   // async (موتور queue در M5)

    'events' => ['created', 'updated', 'deleted', 'login', 'logout', 'permission_changed', 'export', 'config_changed'],

    'exclude_fields' => ['password', 'remember_token', 'secret', 'token', 'two_factor_secret'],

    'retention_days' => 1825,   // ۵ سال

    'immutable_storage' => false,   // WORM (زیرساخت)
];
