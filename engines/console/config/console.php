<?php

declare(strict_types=1);

/*
 * پیکربندی موتور Console — DOC-076
 */

return [
    // دستورات افزودهٔ اپلیکیشن (علاوه بر built-inها و discovery)
    'commands' => [],

    // مسیرهای discovery اضافی
    'paths' => [],

    // کش discovery دستورها
    'cache' => [
        'enabled' => env_bool('TVN_CONSOLE_CACHE', false),
        'file'    => storage_path('cache/commands.php'),
    ],

    'exit_codes' => [
        'success' => 0,
        'failure' => 1,
        'invalid' => 2,
    ],
];
