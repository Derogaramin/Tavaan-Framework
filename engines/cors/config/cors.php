<?php

declare(strict_types=1);

/*
 * پیکربندی CORS — DOC-046 §4
 */

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => env_list('TVN_CORS_ORIGINS'),   // 🔒 پیش‌فرض خالی (امن)
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Tavaan-Ref', 'X-RateLimit-Remaining'],
    'max_age' => 3600,
    'supports_credentials' => false,                      // 🔒 پیش‌فرض false
];
