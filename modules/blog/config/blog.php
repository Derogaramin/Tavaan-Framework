<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول وبلاگ — modules/blog (DOC-091).
 * کلید: config('blog.*')
 */

return [
    // فهرست مقالات
    'per_page' => 10,

    // قالب بدنهٔ مقاله: markdown (escape-first) یا html (سانیتیزه می‌شود)
    'body_format' => 'markdown',

    // پروفایل سانیتایزر بدنه + تگ‌های افزوده بر rich_text
    'sanitize' => [
        'profile' => 'rich_text',
        'extra_tags' => ['h1', 'h4', 'h5', 'h6', 'del', 'hr', 'figure', 'figcaption'],
    ],

    // نظرات (DOC-091 §5-6)
    'comments' => [
        'enabled' => true,
        'guest_allowed' => true,          // ثبت نظر بدون ورود (reader/مهمان)
        'notify_author' => true,          // اعلان ایمیلی به نویسندهٔ مقاله
        'notify_commenter' => true,       // اعلان ایمیلی به نویسندهٔ نظر پس از تأیید
        'max_depth' => 8,                 // سقف عمق رشتهٔ تو‌در‌تو
    ],

    // فید RSS/Atom
    'feed' => [
        'limit' => 20,
        'title' => 'وبلاگ',
        'description' => 'آخرین مقالات وبلاگ',
    ],

    // جست‌وجو (موتور search)
    'search' => [
        'index' => 'blog_posts',
        'enabled' => true,
    ],

    // SEO / sitemap
    'seo' => [
        'sitemap_enabled' => true,
        'default_og_type' => 'article',
    ],

    // Theme override — مسیر ویوهای پوسته (توسط kit:install پر می‌شود)
    // مثال: kits/blog/themes/default/views یا resources/views/vendor/blog
    'theme' => [
        'views_path' => null,
    ],
];
