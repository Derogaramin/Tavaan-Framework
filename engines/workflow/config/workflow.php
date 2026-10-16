<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Workflow
 * config/workflow.php — پیکربندی موتور گردش‌کار (DOC-071).
 */

return [
    // database|array — درایور پایداری (array = حافظه‌ای، برای تست)
    'driver' => env_string('TVN_WORKFLOW_DRIVER', 'database'),

    // نام جداول (DOC-071 §5)
    'tables' => [
        'workflows' => 'tvn_workflows',
        'instances' => 'tvn_process_instances',
        'tasks' => 'tvn_process_tasks',
        'history' => 'tvn_process_history',
    ],

    // 🔒 محافظ DoS: سقف instance فعال هر workflow (0 = نامحدود)
    'max_active_instances' => env_int('TVN_WORKFLOW_MAX_INSTANCES', 0),

    // 🔒 کلیدهایی که در نمایش/لاگ mask می‌شوند (substring match)
    'masked_variables' => ['password', 'token', 'secret', 'card', 'cvv', 'otp'],

    'timers' => [
        'enabled' => env_bool('TVN_WORKFLOW_TIMERS', true),
        // سقف instanceهای بررسی‌شده در هر اجرای workflow:timers
        'batch_size' => env_int('TVN_WORKFLOW_TIMER_BATCH', 100),
    ],
];
