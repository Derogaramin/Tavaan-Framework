<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Filesystem
 * config/filesystem.php — پیکربندی diskها (DOC-063 §4).
 */

return [
    'default' => env_string('TVN_FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'visibility' => 'private',
            'throw' => true,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env_string('TVN_APP_URL', 'http://localhost:8000'), '/') . '/storage',
            'visibility' => 'public',
        ],

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
        ],

        'memory' => [
            'driver' => 'memory',
        ],

        'backups' => [
            'driver' => 'local',
            'root' => storage_path('backups'),
            'visibility' => 'private',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env_string('AWS_KEY', ''),
            'secret' => env_string('AWS_SECRET', ''),
            'region' => env_string('AWS_REGION', 'us-east-1'),
            'bucket' => env_string('AWS_BUCKET', ''),
            'endpoint' => env_string('AWS_ENDPOINT', ''),   // برای S3-compatible (Minio/ArvanCloud)
            'use_path_style' => env_bool('AWS_PATH_STYLE', true),
            'visibility' => 'private',
            'timeout' => 15.0,
            'verify_tls' => env_bool('TVN_S3_VERIFY_TLS', true),
        ],

        'ftp' => [
            'driver' => 'ftp',
            'host' => env_string('FTP_HOST', ''),
            'port' => env_int('FTP_PORT', 21),
            'username' => env_string('FTP_USERNAME', ''),
            'password' => env_string('FTP_PASSWORD', ''),
            'ssl' => env_bool('FTP_SSL', false),
            'root' => env_string('FTP_ROOT', ''),
            'passive' => true,
            'timeout' => 15.0,
        ],
    ],

    // symlinkهای عمومی — با `tvn storage:link` ساخته می‌شوند
    'links' => [
        base_path('public/storage') => storage_path('app/public'),
    ],
];
