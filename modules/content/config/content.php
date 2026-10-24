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
];
