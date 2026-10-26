<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول BPMS — modules/bpms (DOC-098).
 * کلید: config('bpms.*')
 */

return [
    'per_page' => 20,

    // 🔒 سقف نمونه‌های فعال per تعریف (DoS — DOC-071 §8؛ 0 = بدون سقف)
    'max_active_instances' => 500,

    // 🔒 سقف گام‌های advance در یک عملیات (محافظ حلقهٔ service task)
    'advance_limit' => 1000,

    // mirror موتور workflow (تاریخچه/visualization — best-effort؛ الگوی LMS/CRM/ERP)
    'workflow_mirror' => [
        'enabled' => true,
        // prefix نام تعریف mirror در tvn_workflows
        'prefix' => 'bpms-',
    ],

    // تایمر/escalation (§9 — bpms:check-timers هر دقیقه)
    'timers' => [
        'enabled' => true,
        'batch_size' => 100,
        // مقصد پیش‌فرض اکشن داخلی `escalate` (نقش)
        'escalate_role' => 'process-admin',
        // پس از چند بار escalate بی‌نتیجه، کار برای بار چندم فقط اطلاع می‌شود (idempotent)
        'max_escalations' => 3,
    ],

    // اعلان‌ها (§10 — binding notification.message؛ mail+database)
    'notifications' => [
        'process_started' => true,       // → initiator
        'task_created' => true,          // → assignee (user) / کاربران نقش
        'task_completed' => false,       // → initiator (پیش‌فرض خاموش: نویز کم)
        'process_completed' => true,     // → initiator
        'task_escalated' => true,        // → نقش escalation + assignee پیشین
        'escalate_notify_roles' => ['process-admin', 'admin'],
    ],

    // Realtime/SSE (§8 — کانال‌های Broadcast)
    'realtime' => [
        'enabled' => true,
        'channel' => 'bpms',
        'instance_channel_prefix' => 'bpms.instance.',
    ],

    // پایش (§8)
    'monitoring' => [
        'bottleneck_top' => 5,
        'recent_activity' => 12,
    ],

    // 🔒 mask متغیرهای حساس در ویوها/لاگ (الگوی workflow.masked_variables)
    'masked_variables' => ['password', 'secret', 'token', 'national_id', 'card'],

    // audit موتور (عملیات مدیریتی — hash chain؛ fail-open)
    'audit' => [
        'enabled' => true,
    ],
];
