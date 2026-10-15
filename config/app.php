<?php

declare(strict_types=1);

/*
 * پیکربندی اپلیکیشن — DOC-085
 * 🔒 env() فقط در همین فایل‌ها مجاز است.
 */

return [
    'name' => env_string('TVN_APP_NAME', 'Tavaan'),
    'env' => env_string('TVN_APP_ENV', 'production'),
    'debug' => env_bool('TVN_APP_DEBUG', false),
    'url' => env_string('TVN_APP_URL', 'http://localhost'),
    'key' => env_string('TVN_APP_KEY', ''),
    'timezone' => env_string('TVN_APP_TIMEZONE', 'Asia/Tehran'),
    'locale' => env_string('TVN_APP_LOCALE', 'fa'),
    'fallback_locale' => 'en',
    'faker_locale' => 'fa_IR',

    'maintenance' => [
        'driver' => 'file',
        'retry' => 60,
    ],
];
