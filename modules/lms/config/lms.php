<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول LMS — modules/lms (DOC-095).
 * کلید: config('lms.*')
 */

return [
    // پول — ADR-0013 (int minor-unit)
    'currency' => 'IRR',

    'per_page' => 12,

    // بدنهٔ درس/توضیح دوره: markdown escape-first (ماژول content) + sanitize
    'body_format' => 'markdown',
    'sanitize' => [
        'profile' => 'rich_text',
        'extra_tags' => ['h1', 'h4', 'h5', 'h6', 'del', 'hr', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'td', 'th'],
    ],

    // کش فهرست دوره‌ها
    'cache' => [
        'enabled' => true,
        'list_ttl' => 60,
    ],

    // درگاه پرداخت: sandbox (dev/demo/test) یا engine (درگاه واقعی موتور payment)
    'payment' => [
        'driver' => 'sandbox',      // sandbox | engine
        'gateway' => '',            // نام درگاه موتور (zarinpal|idpay|…) — خالی = default
        'callback_url' => '/lms/pay/callback',
    ],

    // آزمون‌ها — پیش‌فرض‌های ساخت آزمون
    'quiz' => [
        'default_pass_score' => 50,     // درصدی از جمع نمرات سؤال‌ها
        'default_max_attempts' => 1,
        'default_time_limit' => null,   // دقیقه (null = بدون محدودیت)
        'auto_grade_short_answer' => true,  // short_answer با پاسخ مرجع → خودکار
    ],

    // گواهی (DOC-095 §3.4 — Workflow: lms-issue-certificate)
    'certificates' => [
        'auto_issue' => true,           // صدور خودکار پس از تکمیل دوره + آزمون‌ها
        'serial_prefix' => 'TVN-LMS',
        'storage_path' => 'certificates',   // روی disk پیش‌فرض (local)
        'verify_base' => '/lms/certificates',   // verify_url نسبی
        'workflow' => 'lms-issue-certificate',
    ],

    // پیشرفت
    'progress' => [
        'complete_quiz_lesson_on_pass' => true,   // درسِ نوع quiz با قبولی آزمون تکمیل می‌شود
    ],

    // اعلان‌ها
    'notifications' => [
        'welcome' => true,          // ثبت‌نام موفق
        'course_completed' => true,
        'certificate_issued' => true,
        'attempt_graded' => true,   // نمره‌دهی دستی تمام شد
    ],

    // جست‌وجو
    'search' => [
        'index' => 'lms_courses',
        'enabled' => true,
    ],

    // پرسش‌وپاسخ درس‌ها (ماژول comment)
    'qa' => [
        'enabled' => true,
        'enrolled_only' => true,    // فقط دانشجوهای ثبت‌نام‌شده سؤال بپرسند
        'auto_approve_staff' => true,   // پاسخ مدرس/assistant بدون moderation
    ],

    // Theme override — توسط kit:install lms پر می‌شود
    'theme' => [
        'views_path' => null,
    ],
];
