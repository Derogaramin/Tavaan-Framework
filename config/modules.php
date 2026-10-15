<?php

declare(strict_types=1);

/*
 * پیکربندی کشف ماژول‌ها — DOC-038 / DOC-022
 */

return [
    'paths' => [
        base_path('engines'),
        base_path('modules'),
    ],
    'enabled' => [],
    'disabled' => [],
    'cache' => [
        'enabled' => env_bool('TVN_MODULE_CACHE', false),
        'file'    => storage_path('cache/modules.php'),
    ],
];
