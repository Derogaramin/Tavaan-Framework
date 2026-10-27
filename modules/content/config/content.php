<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول محتوا — modules/content (DOC-090/DOC-091).
 * کلید: config('content.*')
 */

return [
    // مبدل Markdown (escape-first)
    'markdown' => [
        'allow_images' => true,      // ![alt](url) مجاز باشد؟
    ],

    // فید RSS/Atom
    'feed' => [
        'limit' => 20,               // حداکثر قلم در هر فید
        'language' => 'fa',
    ],

    // خلاصه‌ساز
    'excerpt' => [
        'chars' => 200,
    ],

    // صفحات CMS (Page entity — /pages)
    'pages' => [
        'enabled' => env_bool('TVN_CONTENT_PAGES', true),
        'per_page' => 12,            // فهرست عمومی
        'sitemap_limit' => 500,      // حداکثر ورودی sitemap صفحات
        'body_format' => 'markdown', // markdown (escape-first) یا html (سانیتیزه)
        'sanitize' => [
            'profile' => 'rich_text',
            'extra_tags' => ['h1', 'h4', 'h5', 'h6', 'del', 'hr', 'figure', 'figcaption'],
        ],
        'comments' => true,          // نمایش رشتهٔ نظرات ماژول comment زیر صفحه
    ],
];
