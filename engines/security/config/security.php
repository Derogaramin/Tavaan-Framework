<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Security
 * config/security.php — کنترل‌های امنیتی عرضی (DOC-059 §3 / DOC-016 §3.7).
 */

return [
    'csrf' => [
        'enabled' => true,
        'except' => [],                 // مسیرهای معاف (webhook و …)
        'encrypted_cookie' => false,    // double-submit با کوکی رمزنگاری‌شده
    ],

    'rate_limit' => [
        'enabled' => true,
        'driver' => 'cache',            // cache|redis (M5)
        'algorithm' => 'sliding',       // sliding|fixed|token_bucket
        'default' => ['max' => 60, 'decay' => 60],
    ],

    'headers' => [
        'csp' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
        'hsts' => 'max-age=31536000; includeSubDomains; preload',
        'frame' => 'DENY',
        'referrer' => 'strict-origin-when-cross-origin',
        'permissions' => 'camera=(), microphone=(), geolocation=()',
        'coop' => 'same-origin',
        'corp' => 'same-origin',
        'override' => false,
    ],

    'sanitizers' => [
        'rich_text' => [
            'allowed_tags' => ['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'blockquote', 'code', 'pre', 'img'],
            'allowed_attributes' => [
                'a'   => ['href', 'title', 'rel', 'target'],
                'img' => ['src', 'alt', 'width', 'height'],
            ],
            'allowed_protocols' => ['http', 'https', 'mailto'],
            'strip_comments' => true,
            'remove_scripts' => true,
        ],
        'strict' => [
            'allowed_tags' => [],
            'escape_all' => true,
        ],
        'plain' => [
            'allowed_tags' => [],
            'allowed_attributes' => [],
            'strip_comments' => true,
            'remove_scripts' => true,
        ],
    ],

    'ssrf' => [
        'enabled' => true,
        'allow_private' => false,
        'allowed_hosts' => [],
        'dns_pinning' => true,
    ],

    'normalize' => [
        'unicode' => true,          // NFKC (در صورت وجود intl)
        'strip_bidi' => true,
        'digits_to_latin' => true,
    ],
];
