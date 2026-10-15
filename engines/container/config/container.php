<?php

declare(strict_types=1);

/*
 * پیکربندی موتور Container — DOC-031 §6
 */

return [
    'reflection_cache' => [
        'enabled' => env_bool('TVN_CONTAINER_REFLECTION_CACHE', false),
        'file'    => storage_path('cache/container-reflection.php'),
    ],
    'compiled' => [
        'enabled' => env_bool('TVN_CONTAINER_COMPILED', false),
        'file'    => storage_path('cache/container.php'),
    ],
    'lazy_proxies' => [
        'enabled' => true,
        'targets' => [],
    ],
    'stats' => [
        'enabled' => env_bool('TVN_CONTAINER_STATS', false),
    ],
    'strict' => [
        // 🔒 پارامتر Constructor بدون type → UntypedParameterException
        'allow_untyped_parameters' => false,
        'allow_ambiguous_union'    => false,
        'max_depth'                => 32,
    ],
];
