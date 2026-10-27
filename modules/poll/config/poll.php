<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول نظرسنجی — modules/poll.
 * کلید: config('poll.*')
 */

return [
    'enabled' => env_bool('TVN_POLL', true),
    'per_page' => 10,              // فهرست عمومی
    'admin_per_page' => 15,        // فهرست پنل
    'min_options' => 2,            // حداقل گزینه
    'max_options' => 10,           // حداکثر گزینه

    // رأی‌گیری
    'voting' => [
        'guest_allowed' => true,          // مهمان (بدون ورود) بتواند رأی دهد؟
        'unique_by' => ['user', 'ip'],    // user → رأی یکتای کاربر واردشده؛ ip → هش IP برای مهمان
    ],

    // نمایش نتایج: after_vote | always | closed
    // after_vote → پس از رأی دادن یا بسته شدن نظرسنجی
    'results' => 'after_vote',

    'throttle' => '10,10',         // حداکثر ۱۰ رأی در ۱۰ دقیقه برای هر IP (middleware)
];
