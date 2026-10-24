<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Api
 * config/api.php — پیکربندی لایهٔ API (DOC-044 §8).
 */

return [
    'prefix' => 'api',
    'middleware' => ['api'],

    'pagination' => [
        'per_page' => 20,
        'max_per_page' => 100,
    ],

    'date_format' => \DateTimeInterface::ATOM,
    'preserve_keys' => false,

    'versioning' => [
        // url|header|query
        'strategy' => env_string('TVN_API_VERSIONING', 'url'),
        'default' => 'v1',
        'header' => 'Accept',
        'prefix' => 'application/vnd.tavaan.',
        'supported' => ['v1'],
        'deprecated' => [],
        // 'sunset' => ['v1' => 'Sat, 01 Jan 2028 00:00:00 GMT'],
    ],

    // limiterهای نام‌دار برای throttle:{name}
    'rate_limit' => [
        'api' => ['max' => env_int('TVN_API_RATE_MAX', 60), 'decay' => env_int('TVN_API_RATE_DECAY', 60)],
        'default' => ['max' => 60, 'decay' => 60],
    ],

    'idempotency' => [
        'enabled' => true,
        'ttl' => 86400,   // ۲۴ ساعت
    ],

    'openapi' => [
        'title' => 'Tavaan API',
        'version' => '1.0.0',
        // 'description' => '…',
        'servers' => [
            ['url' => env_string('TVN_APP_URL', 'http://localhost') . '/api/v1', 'description' => 'v1'],
        ],
    ],
];
