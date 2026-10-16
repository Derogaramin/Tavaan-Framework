<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Auth
 * config/auth.php — پیکربندی احراز هویت (DOC-056 §8).
 */

return [
    'defaults' => [
        'guard'     => env_string('TVN_AUTH_GUARD', 'web'),
        'passwords' => 'users',
    ],

    'guards' => [
        'web'   => ['driver' => 'session', 'provider' => 'users'],
        'api'   => ['driver' => 'jwt',     'provider' => 'users'],
        'admin' => ['driver' => 'session', 'provider' => 'users'],
    ],

    'providers' => [
        'users' => [
            'driver' => 'database',           // database|eloquent (ORM)
            'model'  => null,                 // null = GenericUser؛ یا \App\Models\User::class
            'table'  => 'users',
        ],
    ],

    'jwt' => [
        'algorithm'   => 'HS256',             // 🔒 پین‌شده — از header پذیرفته نمی‌شود
        'ttl'         => 3600,
        'refresh_ttl' => 1209600,             // ۱۴ روز
        'issuer'      => env_string('TVN_APP_URL', 'http://localhost'),
        'audience'    => env_string('TVN_APP_URL', 'http://localhost'),
        'leeway'      => 30,
        'keys' => [
            // برای RS256/EdDSA — مسیر فایل کلید (private) و کلید عمومی
            'private_key' => env_string('TVN_JWT_PRIVATE_KEY', ''),
            'public_key'  => env_string('TVN_JWT_PUBLIC_KEY', ''),
            'passphrase'  => env_string('TVN_JWT_PASSPHRASE', ''),
        ],
    ],

    'throttle' => [
        'login' => ['max' => 5, 'decay' => 60, 'lock' => 900],   // ۵ شکست → ۱۵ دقیقه قفل
        'otp'   => ['max' => 5, 'decay' => 300],
        'reset' => ['max' => 3, 'decay' => 3600],
    ],

    'session_hardening' => [
        'regenerate_on_login' => true,
        'fingerprint'         => true,
        'idle_timeout'        => 1800,
        'absolute_timeout'    => 43200,
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_resets',
            'expire'   => 3600,
            'throttle' => 60,
        ],
    ],

    'otp' => [
        'length'   => 6,
        'ttl'      => 120,
        'channels' => ['sms', 'mail'],
        'channel'  => env_string('TVN_OTP_CHANNEL', 'log'),   // log|sms|mail|custom
    ],

    'two_factor' => [
        'enabled'       => true,
        'required_for'  => ['admin', 'super-admin'],
        'window'        => 1,          // ±۱ دوره (RFC 6238)
        'period'        => 30,
        'digits'        => 6,
        'recovery_codes' => 8,
        'table'         => 'two_factor_secrets',
    ],

    'tokens' => [
        'table' => 'personal_access_tokens',
    ],

    'devices' => [
        'table' => 'user_devices',
        'track' => true,
    ],

    'remember' => [
        'cookie'   => 'tavaan_remember',
        'duration' => 2592000,   // ۳۰ روز
    ],
];
