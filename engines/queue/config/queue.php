<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Queue
 * config/queue.php — پیکربندی صف و worker (DOC-061 §8).
 */

return [
    'default' => env_string('TVN_QUEUE_CONNECTION', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'tvn_jobs',
            'connection' => null,          // null = اتصال پیش‌فرض database
            'queue' => 'default',
            'retry_after' => 90,           // ثانیه؛ reserved گیرکرده پس از این مدت برمی‌گردد
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => env_string('TVN_REDIS_HOST', '127.0.0.1'),
            'port' => env_int('TVN_REDIS_PORT', 6379),
            'password' => env_string('TVN_REDIS_PASSWORD', ''),
            'database' => env_int('TVN_REDIS_DB', 0),
            'prefix' => env_string('TVN_QUEUE_PREFIX', 'tvn:'),
            'timeout' => 3.0,
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('queue'),
            'queue' => 'default',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],

    'failed' => [
        'driver' => env_string('TVN_QUEUE_FAILED_DRIVER', 'database'),
        'table' => 'tvn_failed_jobs',
        'dlq' => env_string('TVN_QUEUE_DLQ', ''),   // صف مرده (اختیاری): jobهای شکست‌خورده اینجا هم کپی می‌شوند
    ],

    'batching' => [
        'driver' => env_string('TVN_QUEUE_BATCH_DRIVER', 'database'),
        'table' => 'tvn_job_batches',
    ],

    'worker' => [
        'sleep' => env_int('TVN_QUEUE_SLEEP', 3),
        'max_tries' => env_int('TVN_QUEUE_MAX_TRIES', 3),
        'max_time' => env_int('TVN_QUEUE_MAX_TIME', 0),
        'max_jobs' => env_int('TVN_QUEUE_MAX_JOBS', 0),
        'memory' => env_int('TVN_QUEUE_MEMORY', 128),
    ],

    // 🔒 Allowlist سریال‌سازی — فقط این پیشوندها/کلاس‌ها از payload بازسازی می‌شوند
    'allowlist' => [
        'prefixes' => ['App\\', 'Tavaan\\', 'Modules\\', 'Kits\\', 'Plugins\\'],
        'classes' => [],
    ],
];
