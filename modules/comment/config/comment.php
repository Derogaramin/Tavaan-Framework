<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول نظر — modules/comment (DOC-091 §5).
 * کلید: config('comment.*')
 */

return [
    'moderation' => [
        // manual       → همه pending (پیش‌فرض DOC-091)
        // approve_all  → تأیید خودکار (فقط بررسی اسپم)
        'mode' => env_string('TVN_COMMENT_MODERATION', 'manual'),
        'auto_approve_trusted' => true,   // نویسنده با سابقهٔ تأیید → auto
        'max_links' => 3,                 // لینک بیشتر = اسپم
        'blocked_words' => [],            // واژه‌های مسدود (هرزنگاری)
    ],

    'thread' => [
        'max_depth' => 8,                 // سقف عمق تو‌در‌تو
    ],

    // حالت standalone — ماژول با جدول عمومی comment_comments و
    // endpointهای /comments/{type}/{id} مستقل کار می‌کند.
    'standalone' => [
        'enabled' => env_bool('TVN_COMMENT_STANDALONE', true),
        'guest_allowed' => true,          // ثبت نظر بدون ورود (مهمان)
        'min_length' => 5,                // حداقل طول بدنه
        'max_length' => 5000,             // حداکثر طول بدنه
    ],

    // allowlist انواع نظرپذیر — کلید = type (فقط [a-z0-9_])
    // url: الگوی URL موضوع با {id} (برای لینک «نمایش موضوع» در پنل)
    'types' => [
        'page' => [
            'label' => 'صفحه',
            'url' => '/pages/{id}',
            'guest_allowed' => true,
            'open' => true,
        ],
    ],
];
