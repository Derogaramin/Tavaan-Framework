<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * پیکربندی faq — TVN-FAQ-CFG
 *
 * sanitize با نمایهٔ rich_text و همان زنجیرهٔ ماژول content است
 * (escape-first در Markdown + Security::sanitize لایهٔ دوم).
 */

return [
    'enabled' => env_bool('TVN_FAQ', true),

    // صفحهٔ عمومی /faq (سقف پرسش‌های بارگذاری‌شده)
    'per_page' => (int) env_int('TVN_FAQ_PER_PAGE', 100),

    // صفحه‌بندی پنل مدیریت
    'admin_per_page' => (int) env_int('TVN_FAQ_ADMIN_PER_PAGE', 15),

    // قالب پاسخ: markdown → HTML پاک‌سازی‌شده ذخیره می‌شود
    'body_format' => env('TVN_FAQ_BODY_FORMAT', 'markdown'),

    'sanitize' => [
        'profile' => env('TVN_FAQ_SANITIZE', 'rich_text'),
        'extra_tags' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TVN_FAQ_SANITIZE_EXTRA_TAGS', ''))
        ))),
    ],

    // رأی «مفید بود» — بدون احراز هویت، یک‌بار در هر نشست
    'helpful' => [
        'enabled' => env_bool('TVN_FAQ_HELPFUL', true),
    ],

    // جست‌وجوی عمومی: حداقل طول عبارت
    'search' => [
        'min_length' => (int) env_int('TVN_FAQ_SEARCH_MIN', 2),
    ],
];
