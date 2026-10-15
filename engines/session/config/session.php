<?php

declare(strict_types=1);

/*
 * پیکربندی نشست — DOC-041 §6
 */

return [
    'driver'   => env_string('TVN_SESSION_DRIVER', 'file'),   // file|cookie|array (database|redis در M3/M4)
    'lifetime' => env_int('TVN_SESSION_LIFETIME', 120),       // دقیقه
    'expire_on_close' => false,
    'lottery'  => [2, 100],
    'cookie'   => env_string('TVN_SESSION_COOKIE', 'tavaan_session'),
    'files'    => storage_path('framework/sessions'),
    'path'     => '/',
    'domain'   => env_string('TVN_SESSION_DOMAIN', ''),
    'secure'   => env_bool('TVN_SESSION_SECURE', false),      // 🔒 در prod: true
    'http_only' => true,
    'same_site' => env_string('TVN_SESSION_SAME_SITE', 'Lax'),
    'idle_timeout'     => env_int('TVN_SESSION_IDLE', 1800),
    'absolute_timeout' => env_int('TVN_SESSION_ABSOLUTE', 43200),
    'fingerprint'      => true,
    'csrf' => [
        'except' => [],        // مسیرهای معاف (webhook و …)
    ],
];
