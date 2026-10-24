<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول فروشگاه — modules/shop (DOC-093).
 * کلید: config('shop.*')
 */

return [
    // پول — ADR-0013 (int minor-unit)
    'currency' => 'IRR',

    'per_page' => 12,

    'body_format' => 'markdown',
    'sanitize' => [
        'profile' => 'rich_text',
        'extra_tags' => ['h1', 'h4', 'h5', 'h6', 'del', 'hr', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'td', 'th'],
    ],

    // کش (DOC-093 §9)
    'cache' => [
        'enabled' => true,
        'list_ttl' => 60,
    ],

    // مالیات — درصد پیش‌فرض (پیکربندی سازمانی در preset کیت)
    'tax' => [
        'percent' => 0.0,           // ۰ = بدون مالیات (پیش‌فرض توسعه)
        'shipping_taxable' => false,
    ],

    // ارسال — نرخ تخت + آستانهٔ ارسال رایگان (minor-unit)
    'shipping' => [
        'flat' => 500_000,          // ۵۰۰٬۰۰۰ ریال
        'free_threshold' => 50_000_000, // ارسال رایگان بالای ۵۰ میلیون ریال
    ],

    // درگاه پرداخت: sandbox (dev/demo/test) یا engine (درگاه واقعی موتور payment)
    'payment' => [
        'driver' => 'sandbox',      // sandbox | engine
        'gateway' => '',            // نام درگاه موتور (zarinpal|idpay|…) — خالی = default
        'callback_url' => '/shop/pay/callback',
    ],

    // نقد و بررسی
    'reviews' => [
        'enabled' => true,
        'guest_allowed' => true,
    ],

    // اعلان‌ها
    'notifications' => [
        'order_paid' => true,
        'order_shipped' => true,
        'order_canceled' => true,
    ],

    // جست‌وجو
    'search' => [
        'index' => 'shop_products',
        'enabled' => true,
    ],

    'seo' => [
        'sitemap_enabled' => true,
    ],

    // Theme override — توسط kit:install shop پر می‌شود
    'theme' => [
        'views_path' => null,
    ],
];
