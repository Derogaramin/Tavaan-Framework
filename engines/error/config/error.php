<?php

declare(strict_types=1);

use Tavaan\Core\Exceptions\Auth\AuthenticationException;
use Tavaan\Core\Exceptions\Authorization\AuthorizationException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;

/*
 * پیکربندی موتور Error — DOC-035 §6
 */

return [
    'environment' => env_string('TVN_APP_ENV', 'production'),

    'reporting' => [
        'level_map' => [
            ValidationException::class => 'info',
        ],
        'dont_report' => [
            ValidationException::class,
            RouteNotFoundException::class,
            AuthorizationException::class,
            AuthenticationException::class,
        ],
        'report_client_errors' => false,
        'max_context_depth'    => 8,
    ],

    'rendering' => [
        'debug'         => env_bool('TVN_APP_DEBUG', false),
        'renderer'      => 'auto',
        'json_format'   => 'rfc9457',
        'show_ref_code' => true,
        'support_url'   => env_string('TVN_SUPPORT_URL', ''),
    ],

    'deprecations' => [
        'fail_in_ci'      => env_bool('TVN_FAIL_ON_DEPRECATION', false),
        'log_channel'     => 'deprecations',
        'max_per_request' => 10,
    ],

    'masking' => [
        'extra_keys'     => [],
        'extra_patterns' => [],
        'disable'        => false,
    ],

    'fatal' => [
        'memory_limit_reserve' => 262_144,
        'safe_page'            => true,
    ],
];
