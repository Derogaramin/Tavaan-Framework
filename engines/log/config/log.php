<?php

declare(strict_types=1);

/*
 * پیکربندی موتور Log — DOC-036 §8
 */

return [
    'default' => env_string('TVN_LOG_CHANNEL', 'daily'),

    'channels' => [
        'daily' => [
            'driver'     => 'daily',
            'path'       => storage_path('logs/tavaan.log'),
            'level'      => env_string('TVN_LOG_LEVEL', 'debug'),
            'days'       => 14,
            'permission' => 0640,
            'formatter'  => env_string('TVN_LOG_FORMAT', 'line'),
            'processors' => ['mask', 'trace', 'identity', 'memory', 'runtime', 'backtrace', 'environment'],
            'buffer'     => ['enabled' => true, 'max_lines' => 50],
        ],

        'json' => [
            'driver'     => 'daily',
            'path'       => storage_path('logs/tavaan.json.log'),
            'formatter'  => 'json',
            'days'       => 30,
            'processors' => ['mask', 'trace', 'identity', 'environment'],
        ],

        'audit' => [
            'driver'     => 'daily',
            'path'       => storage_path('logs/audit.log'),
            'level'      => 'info',
            'days'       => 1825,
            'formatter'  => 'json',
            'locking'    => true,
            'processors' => ['mask', 'trace', 'identity', 'environment'],
        ],

        'query' => [
            'driver'     => 'daily',
            'path'       => storage_path('logs/query.log'),
            'level'      => 'debug',
            'days'       => 3,
            'enabled'    => env_bool('TVN_LOG_QUERY', false),
            'formatter'  => 'json',
            'processors' => ['mask', 'trace'],
        ],

        'queue' => [
            'driver'     => 'daily',
            'path'       => storage_path('logs/queue.log'),
            'level'      => 'info',
            'days'       => 7,
            'formatter'  => 'json',
            'processors' => ['mask', 'trace', 'identity'],
        ],

        'security' => [
            'driver'     => 'daily',
            'path'       => storage_path('logs/security.log'),
            'level'      => 'warning',
            'days'       => 180,
            'formatter'  => 'json',
            'processors' => ['mask', 'trace', 'identity', 'environment'],
        ],

        'deprecations' => [
            'driver'     => 'daily',
            'path'       => storage_path('logs/deprecations.log'),
            'level'      => 'warning',
            'days'       => 30,
            'processors' => ['mask', 'backtrace'],
        ],

        'stderr' => ['driver' => 'stderr', 'formatter' => 'json', 'level' => 'error'],
        'stack'  => ['driver' => 'stack', 'channels' => ['daily', 'stderr'], 'ignore_exceptions' => false],
        'null'   => ['driver' => 'null'],
        'array'  => ['driver' => 'array'],
    ],

    'masking' => [
        'keys'     => [],
        'patterns' => [],
    ],

    'limits' => [
        'max_context_depth'  => 8,
        'max_context_bytes'  => 8192,
        'max_message_length' => 2048,
        'per_request_limit'  => 500,
    ],
];
