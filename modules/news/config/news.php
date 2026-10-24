<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول خبرگزاری — modules/news (DOC-092).
 * کلید: config('news.*')
 */

return [
    // فهرست اخبار
    'per_page' => 12,

    // قالب بدنه: markdown (escape-first) یا html (سانیتیزه)
    'body_format' => 'markdown',

    'sanitize' => [
        'profile' => 'rich_text',
        'extra_tags' => ['h1', 'h4', 'h5', 'h6', 'del', 'hr', 'figure', 'figcaption'],
    ],

    // 🔒 کش — برای خبری بحرانی است (DOC-092 §5)
    'cache' => [
        'enabled' => true,
        'list_ttl' => 60,        // فهرست‌ها: ۶۰s + invalidate با tag
        'article_ttl' => 300,    // صفحهٔ خبر: ۳۰۰s + ETag/304
        'breaking_ttl' => 10,    // breaking: ۱۰s
    ],

    // خبر فوری (DOC-092 §6)
    'breaking' => [
        'limit' => 5,
        'banner' => true,
    ],

    // realtime — broadcast خبر فوری روی کانال news
    'realtime' => [
        'enabled' => true,
        'channel' => 'news',
    ],

    // نظرات
    'comments' => [
        'enabled' => true,
        'guest_allowed' => true,
        'notify_author' => true,
        'max_depth' => 8,
    ],

    // اعلان‌های workflow (به نویسنده)
    'notifications' => [
        'workflow_approved' => true,
        'workflow_rejected' => true,
        'workflow_published' => true,
    ],

    // فید
    'feed' => [
        'limit' => 30,
        'title' => 'خبرگزاری',
        'description' => 'آخرین اخبار',
        'language' => 'fa',
    ],

    // جست‌وجو
    'search' => [
        'index' => 'news_articles',
        'enabled' => true,
    ],

    'seo' => [
        'sitemap_enabled' => true,
    ],

    // Theme override — توسط kit:install news پر می‌شود
    'theme' => [
        'views_path' => null,
    ],
];
