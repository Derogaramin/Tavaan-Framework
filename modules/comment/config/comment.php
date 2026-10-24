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
        'mode' => 'manual',
        'auto_approve_trusted' => true,   // نویسنده با سابقهٔ تأیید → auto
        'max_links' => 3,                 // لینک بیشتر = اسپم
        'blocked_words' => [],            // واژه‌های مسدود (هرزنگاری)
    ],

    'thread' => [
        'max_depth' => 8,                 // سقف عمق تو‌در‌تو
    ],
];
