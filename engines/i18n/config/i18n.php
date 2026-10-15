<?php

declare(strict_types=1);

/*
 * پیکربندی موتور I18n — DOC-069 §8
 */

return [
    'locale' => env_string('TVN_APP_LOCALE', 'fa'),
    'fallback_locale' => env_string('TVN_FALLBACK_LOCALE', 'en'),
    'available' => ['fa', 'en', 'ar'],
    'rtl_locales' => ['fa', 'ar', 'he', 'ur'],
    'paths' => [
        base_path('resources/lang'),
    ],
    'namespaces' => [],       // توسط ماژول‌ها افزوده می‌شود (addNamespace)
    'cache' => [
        'enabled' => env_bool('TVN_LANG_CACHE', false),
        'file'    => storage_path('cache/lang.php'),
    ],
    'missing_key_handler' => 'log',   // log|exception|key
    'digits' => env_string('TVN_DIGITS', 'persian'),   // persian|latin|auto
];
