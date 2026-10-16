<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Notification
 * config/notification.php — پیکربندی آگاه‌سازی چندکاناله (DOC-066 §6).
 */

return [
    // کانال‌های فعال (خالی = همه)
    'channels' => ['mail', 'sms', 'database', 'broadcast', 'webhook', 'telegram', 'bale', 'eitaa', 'push'],

    'database' => [
        'table' => 'tvn_notifications',
    ],

    // آگاه‌سازی‌های ShouldQueue به صف بروند
    'queue' => env_bool('TVN_NOTIFICATION_QUEUE', true),

    'sms' => [
        'driver' => env_string('TVN_SMS_DRIVER', 'log'),
        'gateways' => [
            'kavenegar' => [
                'api_key' => env_string('KAVENEGAR_KEY', ''),
                'sender' => env_string('KAVENEGAR_SENDER', ''),
            ],
            'melipayamak' => [
                'username' => env_string('MP_USER', ''),
                'password' => env_string('MP_PASS', ''),
                'sender' => env_string('MP_SENDER', ''),
            ],
            'ghasedak' => [
                'api_key' => env_string('GHASEDAK_KEY', ''),
                'sender' => env_string('GHASEDAK_SENDER', ''),
            ],
            'smsir' => [
                'api_key' => env_string('SMSIR_KEY', ''),
                'sender' => env_string('SMSIR_SENDER', ''),
            ],
            'farazsms' => [
                'username' => env_string('FARAZ_USER', ''),
                'password' => env_string('FARAZ_PASS', ''),
                'sender' => env_string('FARAZ_SENDER', ''),
            ],
        ],
    ],

    'telegram' => [
        'token' => env_string('TELEGRAM_BOT_TOKEN', ''),
        'base_url' => env_string('TELEGRAM_API_URL', 'https://api.telegram.org'),
    ],

    'bale' => [
        'token' => env_string('BALE_BOT_TOKEN', ''),
        'base_url' => env_string('BALE_API_URL', 'https://tapi.bale.ai'),
    ],

    'eitaa' => [
        'token' => env_string('EITAA_BOT_TOKEN', ''),
        'base_url' => env_string('EITAA_API_URL', 'https://bot.eitaa.com/api'),
    ],

    'push' => [
        'endpoint' => env_string('FCM_ENDPOINT', 'https://fcm.googleapis.com/fcm/send'),
        'server_key' => env_string('FCM_SERVER_KEY', ''),
    ],

    // 🔒 Rate Limit — جلوگیری از SMS Bombing (DOC-066 §7)
    'rate_limit' => [
        'per_user_per_hour' => env_int('TVN_NOTIFICATION_RATE_LIMIT', 20),
    ],

    'log_sent' => env_bool('TVN_NOTIFICATION_LOG_SENT', true),
];
