<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول انجمن — modules/forum (DOC-094).
 * کلید: config('forum.*')
 */

return [
    // فهرست‌ها
    'per_page' => 20,          // موضوعات در تالار
    'posts_per_page' => 20,    // پست‌ها در موضوع

    // قالب بدنه: markdown (escape-first) یا html (سانیتیزه)
    'body_format' => 'markdown',

    'sanitize' => [
        'profile' => 'rich_text',
        'extra_tags' => ['blockquote', 'pre', 'code', 'del', 'hr'],
    ],

    // 🔒 Anti-spam (DOC-094 §6/§7)
    'flood' => [
        'enabled' => true,
        'min_seconds' => 30,        // حداقل فاصلهٔ بین دو پست هر کاربر
        'window_seconds' => 300,    // پنجرهٔ شمارش
        'max_posts' => 10,          // حداکثر پست در پنجره
    ],

    'spam' => [
        'max_links' => 3,           // لینک بیش از این → اسپم
        'blocked_words' => [],      // واژه‌های مسدود (lowercase)
        'honeypot_field' => 'website',   // فیلد نامرئی — باید خالی بماند
    ],

    // 🔒 Reputation/Karma (DOC-094 §6)
    'karma' => [
        'enabled' => true,
        'upvote_received' => 2,     // هر +1 دریافتی
        'downvote_received' => -1,  // هر -1 دریافتی
        'solution_author' => 15,    // پاسخ برگزیدهٔ نویسنده
        'thread_created' => 1,
        'post_created' => 1,
    ],

    // ذکر کاربر (@username) با اعلان (DOC-094 §6)
    'mentions' => [
        'enabled' => true,
        'max_per_post' => 10,
    ],

    // اشتراک موضوع — اعلان به دنبال‌کنندگان
    'subscriptions' => [
        'notify_on_reply' => true,
        'auto_subscribe_author' => true,
    ],

    // realtime — broadcast پست/موضوع جدید (DOC-094 §6 + AC-FORUM-08: SSE بدون ext)
    'realtime' => [
        'enabled' => true,
        'channel' => 'forum',              // کانال سراسری
        'thread_channel_prefix' => 'forum.thread.',   // + ULID موضوع
    ],

    // Moderation queue (DOC-094 §5)
    'moderation' => [
        'hide_on_resolve' => true,     // flag → resolve یعنی پنهان‌کردن پست
        'auto_hide_threshold' => 0,    // 0 = خاموش؛ n = پنهان خودکار پس از n flag
        'flag_once_per_user' => true,
    ],

    // اعلان‌ها
    'notifications' => [
        'reply_to_author' => true,     // پاسخ → نویسندهٔ موضوع
        'reply_to_subscribers' => true,
        'mention' => true,
        'solution' => true,            // پاسخ شما برگزیده شد
        'flagged_to_moderators' => true,
        'thread_created_to_moderators' => true,
        'moderator_roles' => ['moderator', 'admin'],   // نقش‌های دریافت اعلان صف
    ],

    // جست‌وجو (موتور search — ایندکس رویدادمحور + fallback)
    'search' => [
        'enabled' => true,
        'threads_index' => 'forum_threads',
        'posts_index' => 'forum_posts',
    ],

    // کش فهرست‌ها
    'cache' => [
        'enabled' => true,
        'ttl' => 60,
        'tag' => 'forum',
    ],

    // نقل‌قول (DOC-094 §6)
    'quote' => [
        'max_length' => 800,   // حداکثر کاراکتر نقل‌قول
    ],

    // پروفایل انجمن — username
    'profile' => [
        'username_min' => 3,
        'username_max' => 30,
        'allow_username_change' => true,
    ],

    // Theme override — توسط kit:install forum پر می‌شود
    'theme' => [
        'views_path' => null,
    ],
];
