<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Cache
 * config/cache.php — پیکربندی حافظهٔ نهان (DOC-060 §7).
 */

return [
    'default' => env_string('TVN_CACHE_DRIVER', 'file'),

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache/data'),
            'lock_path' => storage_path('cache/locks'),
            'permission' => 0664,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,          // null = default
            'table' => 'cache',
            'lock_table' => 'cache_locks',
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => env_string('TVN_REDIS_HOST', '127.0.0.1'),
            'port' => env_int('TVN_REDIS_PORT', 6379),
            'password' => env_string('TVN_REDIS_PASSWORD', ''),
            'database' => env_int('TVN_REDIS_DB', 0),
            'prefix' => env_string('TVN_CACHE_PREFIX', 'tvn:'),
            'timeout' => 2.0,
        ],

        'memcached' => [
            'driver' => 'memcached',
            'servers' => [
                ['host' => env_string('TVN_MEMCACHED_HOST', '127.0.0.1'), 'port' => env_int('TVN_MEMCACHED_PORT', 11211), 'weight' => 100],
            ],
            'prefix' => env_string('TVN_CACHE_PREFIX', 'tvn:'),
        ],

        'apcu' => [
            'driver' => 'apcu',
            'prefix' => env_string('TVN_CACHE_PREFIX', 'tvn:'),
        ],
    ],

    // درایورهای اشتراکی پشتیبانی Tag هستند (namespace-based)
    'tags' => [
        'enabled' => true,
        'prefix' => 'tvn:tag:',
    ],

    'lock' => [
        'timeout' => 5,       // ثانیه پیش‌فرض block
        'retry' => 100,       // ms بین تلاش‌ها
    ],

    'stampede_protection' => [
        'enabled' => true,
        'lock_ttl' => 10,
        'serve_stale' => true,
        'stale_grace' => 60,   // ثانیه اضافه برای نسخهٔ stale
        'max_wait_retries' => 3,
    ],

    // 🔒 DoS
    'max_key_length' => 512,
    'max_value_size' => 5242880,   // 5MB
];
