<?php

declare(strict_types=1);

/*
 * پیکربندی موتور Config — DOC-032 §6
 */

return [
    'env_file' => env_string('TVN_ENV_FILE', base_path('.env')),
    'env_fallback' => [base_path('.env.local')],
    'cache' => [
        'enabled' => env_bool('TVN_CONFIG_CACHE', false),
        'file'    => storage_path('cache/config.php'),
    ],
    'schema' => [
        'enabled' => env_bool('TVN_CONFIG_SCHEMA', true),
        'path'    => base_path('config/schema'),
        'strict'  => env_string('TVN_APP_ENV', 'production') !== 'production',
    ],
    'freeze_in_production' => true,
    'secret_keys' => ['*.password', '*.secret', '*.key', '*.token', 'db.*.password', 'mail.*.password'],
];
