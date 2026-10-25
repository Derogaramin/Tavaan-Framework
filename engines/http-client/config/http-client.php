<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — HttpClient
 * config/http-client.php — پیکربندی کلاینت HTTP (DOC-064 §6).
 */

return [
    'timeout' => env_int('TVN_HTTP_TIMEOUT', 10),
    'connect_timeout' => env_int('TVN_HTTP_CONNECT_TIMEOUT', 5),

    'retry' => [
        'times' => env_int('TVN_HTTP_RETRY_TIMES', 1),   // ۱ = بدون retry پیش‌فرض
        'sleep' => env_int('TVN_HTTP_RETRY_SLEEP', 100), // میلی‌ثانیه (پایهٔ نمایی)
        'backoff' => 'exponential',
    ],

    'verify_tls' => env_bool('TVN_HTTP_VERIFY_TLS', true),
    'ca_bundle' => env_string('TVN_HTTP_CA_BUNDLE', '') ?: null,
    'proxy' => env_string('TVN_HTTP_PROXY', '') ?: null,
    'user_agent' => env_string('TVN_HTTP_USER_AGENT', 'Tavaan/1.0 (+https://tavaan.dev)') ?: 'Tavaan/1.0 (+https://tavaan.dev)',

    // 🔒 SSRF
    'ssrf_protection' => env_bool('TVN_HTTP_SSRF', true),
    'ssrf' => [
        'allowed_hosts' => [],        // خالی = همه (با SSRF guard)
        'allow_private' => env_bool('TVN_HTTP_ALLOW_PRIVATE', false),
        'allowed_ports' => [80, 443, 8080, 8443],
    ],

    // سقف حجم پاسخ (بایت؛ ۰ = نامحدود) — DoS با پاسخ بزرگ
    'max_size' => env_int('TVN_HTTP_MAX_SIZE', 0),

    'pool_concurrency' => env_int('TVN_HTTP_POOL_CONCURRENCY', 10),

    'circuit_breaker' => [
        'threshold' => env_int('TVN_HTTP_CB_THRESHOLD', 5),
        'window' => env_int('TVN_HTTP_CB_WINDOW', 60),
        'cooldown' => env_int('TVN_HTTP_CB_COOLDOWN', 30),
    ],
];
