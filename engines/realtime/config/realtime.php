<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Realtime
 * config/realtime.php — پیکربندی بلادرنگ (DOC-045 §9).
 */

return [
    // sse|websocket
    'driver' => env_string('TVN_REALTIME_DRIVER', 'sse'),

    // array|database|redis
    'broker' => env_string('TVN_REALTIME_BROKER', 'array'),

    // کلید امضای توکن احراز کانال (خالی = app.key)
    'auth_secret' => '',
    'auth_ttl' => 3600,

    'sse' => [
        'endpoint' => '/events/stream',
        'heartbeat' => 15,           // ثانیه
        'retry_ms' => 3000,          // reconnect کلاینت
        'max_connections' => 1000,
        'max_runtime' => 0,          // ۰ = تا قطع کلاینت
    ],

    'websocket' => [
        'host' => env_string('TVN_WS_HOST', '0.0.0.0'),
        'port' => env_int('TVN_WS_PORT', 8081),
        'max_clients' => 1000,
        // 🔒 خالی = بدون محدودیت (dev)؛ در production حتماً پر شود
        'allowed_origins' => [],
    ],

    'database' => [
        'table' => 'tvn_broadcast_events',
        'ttl' => 3600,
    ],

    'redis' => [
        'host' => env_string('TVN_REDIS_HOST', '127.0.0.1'),
        'port' => env_int('TVN_REDIS_PORT', 6379),
        'password' => env_string('TVN_REDIS_PASSWORD', ''),
        'database' => env_int('TVN_REDIS_DB', 0),
        'prefix' => env_string('TVN_REDIS_PREFIX', 'tvn:'),
        'cap' => 1000,               // سقف رویداد در هر کانال (LTRIM)
    ],

    'array' => [
        'cap' => 1000,
    ],

    'presence' => [
        'ttl' => 90,                 // ثانیه — با heartbeat تمدید می‌شود
    ],

    'auth_endpoint' => '/broadcasting/auth',
];
