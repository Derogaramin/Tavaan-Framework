<?php

declare(strict_types=1);

/*
 * پیکربندی موتور HTTP — DOC-039 §9
 */

return [
    'driver'          => env_string('TVN_HTTP_DRIVER', 'builtin'),
    'host'            => env_string('TVN_HTTP_HOST', '127.0.0.1'),
    'port'            => env_int('TVN_HTTP_PORT', 8000),

    'trusted_proxies' => env_list('TVN_TRUSTED_PROXIES'),   // 🔒 پیش‌فرض خالی = امن
    'trusted_headers' => ['X-Forwarded-For', 'X-Forwarded-Host', 'X-Forwarded-Proto', 'X-Forwarded-Port'],
    'trusted_hosts'   => env_list('TVN_TRUSTED_HOSTS'),
    'allowed_redirect_hosts' => [],

    'max_body_size'   => 8 * 1024 * 1024,
    'max_json_depth'  => 32,
    'max_json_size'   => 4 * 1024 * 1024,
    'max_upload_size' => 16 * 1024 * 1024,

    'method_override' => true,
    'normalize_path'  => true,

    'etag'        => ['enabled' => true, 'algorithm' => 'xxh128'],
    'compression' => ['enabled' => true, 'min_size' => 1024, 'level' => 5, 'algorithms' => ['gzip', 'deflate']],

    'security_headers' => [],          // خالی = پیش‌فرض‌های SecurityHeadersMiddleware
    'security_headers_override' => false,

    'maintenance' => [
        'except' => ['health', 'up'],
    ],

    'middleware' => [
        'global' => [
            \Tavaan\Engine\Http\Middleware\TrustProxiesMiddleware::class,
            \Tavaan\Engine\Http\Middleware\MaintenanceModeMiddleware::class,
            \Tavaan\Engine\Http\Middleware\TraceContextMiddleware::class,
            \Tavaan\Engine\Http\Middleware\SecurityHeadersMiddleware::class,
            \Tavaan\Engine\Http\Middleware\RequestSizeLimitMiddleware::class,
            \Tavaan\Engine\Http\Cookie\EncryptCookiesMiddleware::class,
            \Tavaan\Engine\Session\Middleware\StartSessionMiddleware::class,
            \Tavaan\Engine\Session\Middleware\ShareErrorsFromSessionMiddleware::class,
            \Tavaan\Engine\Session\Middleware\CsrfVerifyMiddleware::class,
            \Tavaan\Engine\Http\Cors\CorsMiddleware::class,
            \Tavaan\Engine\Http\Middleware\SetLocaleMiddleware::class,
        ],
        'groups' => [
            'web' => [],
            'api' => [],
        ],
        'aliases' => [],
    ],
];
