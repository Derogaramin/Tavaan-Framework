<?php

declare(strict_types=1);

/*
 * قوانین arch:check (مرزهای لایه‌ای — DOC-021/DOC-022).
 *
 * 🔒 ممیزی M0–M8: دو افزودنی مستند:
 *  ۱) except برای Tavaan\Core\Contracts\ — برخی contractهای هسته به VOهای
 *     موتور تایپ شده‌اند (SearchResult، LogRecord، Payment VOها، Cookie).
 *     این «coupling عمل‌گرایانه» تا زمان انتقال VOها به هسته (بدهی فنی
 *     TECH-DEBT-ARCH-01 — گزارش ممیزی M0–M8) مستثنی است؛ خودِ contractها
 *     در بوت بدون موتور load نمی‌شوند.
 *  ۲) forbidden_functions_allow — allowlist صریح و مستند نقطه‌هایی که
 *     اجرای subprocess/extract در آن‌ها قابلیتِ ذاتاً مستند است (هم‌راستا
 *     با tools/scripts/check-forbidden.php).
 */

return [
    'rules' => [
        // core هرگز به engines/modules/app وابسته نباشد
        // (به‌جز composition root در Core\Bootstrap و contractهای VO-محور — بالا)
        ['from' => 'Tavaan\\Core\\', 'deny' => ['Tavaan\\Engine\\', 'Modules\\', 'App\\'],
         'except' => ['Tavaan\\Core\\Bootstrap\\', 'Tavaan\\Core\\Contracts\\']],
        // موتور فقط به core و contractها
        ['from' => 'Tavaan\\Engine\\', 'deny' => ['Modules\\', 'App\\']],
        // ماژول به موتور دیگر فقط از طریق Contract/Event/Facade هسته.
        // allow = نقطه‌های تمدید SDK (صریح و مستند — ممیزی M9):
        //   Console\Command: پایهٔ دستورات CLI ماژول‌ها (کشف خودکار
        //   modules/*&#47;src/Console/Commands بدون آن ممکن نیست — DOC-076/DOC-080).
        ['from' => 'Modules\\', 'deny' => ['Tavaan\\Engine\\'],
         'allow' => ['Tavaan\\Engine\\Console\\Command']],
    ],
    'forbidden_functions' => [
        'eval', 'create_function', 'putenv', 'shell_exec', 'exec', 'system', 'passthru',
        'popen', 'proc_open', 'extract', 'compact', 'goto',
    ],
    // allowlist صریح: تابع → مسیرهای مجاز (prefix). هر افزودنی باید دلیل مستند داشته باشد.
    'forbidden_functions_allow' => [
        'extract' => [
            'engines/view/src/ViewFactory.php',   // scope ویو با EXTR_SKIP — ممیزی M1–M4
        ],
        'exec' => [
            'engines/schedule/src/ScheduleManager.php',   // Schedule::exec() — DOC-062
        ],
        'shell_exec' => [
            'engines/schedule/src/ScheduleManager.php',   // DOC-062
            'engines/console/src/Commands/',              // ابزارهای QA (style:check)
        ],
        'passthru' => [
            'engines/http/src/Console/ServeCommand.php',  // سرور توسعه — DOC-083
        ],
        'proc_open' => [
            'engines/console/src/Commands/',              // test --parallel، lint:php، bench:all، style:check
            'engines/view/src/ViewFactory.php',           // php -l روی template کامپایل‌شده (تشخیص)
            'engines/mail/src/Transports/SendmailTransport.php',  // sendmail binary
            'engines/queue/src/Console/QueueListenCommand.php',   // supervisor worker
        ],
    ],
    'forbidden_patterns' => [
        '/\$\{[a-zA-Z_]/',            // F-02: ${var} interpolation
        '/\bprint_r\s*\(/',
        '/\bmt_rand\s*\(/',           // F-20
        '/\bunserialize\s*\(/',       // F-21
    ],
];
