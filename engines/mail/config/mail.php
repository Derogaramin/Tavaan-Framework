<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Mail
 * config/mail.php — پیکربندی ایمیل (DOC-065 §7).
 */

return [
    'default' => env_string('TVN_MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env_string('TVN_MAIL_HOST', 'localhost'),
            'port' => env_int('TVN_MAIL_PORT', 587),
            'encryption' => env_string('TVN_MAIL_ENCRYPTION', 'tls'),   // tls (STARTTLS) | ssl | ''
            'username' => env_string('TVN_MAIL_USERNAME', ''),
            'password' => env_string('TVN_MAIL_PASSWORD', ''),
            'timeout' => env_int('TVN_MAIL_TIMEOUT', 10),
            'auth_mode' => env_string('TVN_MAIL_AUTH_MODE', ''),        // login | plain | ''
            'local_domain' => env_string('TVN_MAIL_LOCAL_DOMAIN', 'tavaan.local'),
        ],

        'ses' => [
            'transport' => 'ses',
            'key' => env_string('AWS_KEY', ''),
            'secret' => env_string('AWS_SECRET', ''),
            'region' => env_string('AWS_REGION', 'us-east-1'),
            'timeout' => 15.0,
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env_string('TVN_MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -t -i'),
        ],

        'mail' => [
            'transport' => 'mail',
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env_string('TVN_MAIL_LOG_CHANNEL', 'mail'),
        ],

        'array' => [
            'transport' => 'array',
        ],
    ],

    'from' => [
        'address' => env_string('TVN_MAIL_FROM_ADDRESS', 'noreply@tavaan.local'),
        'name' => env_string('TVN_MAIL_FROM_NAME', 'تَوان'),
    ],
];
