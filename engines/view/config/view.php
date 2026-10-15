<?php

declare(strict_types=1);

/*
 * پیکربندی موتور View — DOC-043 §9
 */

return [
    'paths' => [
        base_path('resources/views'),
        base_path('app/views'),
    ],
    'extension'   => '.tv.php',
    'compiled'    => storage_path('framework/views'),
    'cache'       => env_bool('TVN_VIEW_CACHE', false),
    'auto_escape' => true,           // 🔒 هرگز در prod خاموش نشود
    'namespaces'  => [],
    'components'  => [
        'paths'      => [base_path('resources/views/components')],
        'namespaces' => ['x' => 'components'],
    ],
    'directives' => [],
    'shared'     => [],
    'max_depth'  => 10,
    'hmvc'       => ['max_depth' => 4, 'isolate_errors' => true],
];
