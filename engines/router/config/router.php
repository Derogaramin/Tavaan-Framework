<?php

declare(strict_types=1);

/*
 * پیکربندی موتور Router — DOC-040 §10
 */

return [
    'files' => [
        base_path('routes/web.php')   => ['middleware' => ['web']],
        base_path('routes/api.php')   => ['middleware' => ['api'], 'prefix' => 'api'],
        base_path('routes/admin.php') => ['middleware' => ['web'], 'prefix' => 'admin'],
    ],
    'cache' => [
        'enabled' => env_bool('TVN_ROUTE_CACHE', false),
        'file'    => storage_path('cache/routes.php'),
    ],
    'matching' => [
        'trailing_slash' => 'ignore',
        'case_sensitive' => false,
        'normalize'      => true,
        'prefer_static'  => true,
    ],
    'model_binding' => [
        'soft_deletes'   => false,
        'enforce_policy' => true,
        'scope_bindings' => true,
    ],
    'attributes' => [
        'enabled' => false,
        'paths'   => [base_path('app/src/Http/Controllers')],
    ],
    'fallback' => [
        'enabled' => true,
        'view'    => 'errors.404',
    ],
    'url' => [
        'force_https' => env_bool('TVN_FORCE_HTTPS', false),
        'asset_url'   => env_string('TVN_ASSET_URL', ''),
    ],
];
