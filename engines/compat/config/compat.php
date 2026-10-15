<?php

declare(strict_types=1);

/*
 * پیکربندی موتور Compat — DOC-083E §5
 */

return [
    'force_fallbacks' => env_bool('TVN_COMPAT_FORCE_FALLBACK', false),
    'drivers' => [
        'math'    => env_string('TVN_MATH_DRIVER', 'auto'),
        'image'   => env_string('TVN_IMAGE_DRIVER', 'auto'),
        'zip'     => env_string('TVN_ZIP_DRIVER', 'auto'),
        'http'    => env_string('TVN_HTTP_DRIVER', 'auto'),
        'unicode' => env_string('TVN_UNICODE_DRIVER', 'auto'),
        'mime'    => env_string('TVN_MIME_DRIVER', 'auto'),
    ],
    'warn_on_fallback' => env_bool('TVN_COMPAT_WARN', true),
];
