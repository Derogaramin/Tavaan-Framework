<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول CRM — modules/crm (DOC-096).
 * کلید: config('crm.*')
 */

return [
    // پول — ADR-0013 (int minor-unit)
    'currency' => 'IRR',

    'per_page' => 20,

    // Pipeline — مرحله → احتمال پیش‌فرض (DOC-096 §3.1: weighted forecast)
    'pipeline' => [
        'workflow' => 'crm-sales-pipeline',
        'probabilities' => [
            'prospecting' => 10,
            'qualification' => 25,
            'proposal' => 50,
            'negotiation' => 75,
            'closed_won' => 100,
            'closed_lost' => 0,
        ],
    ],

    // Lead Scoring — وزن‌های DOC-096 §3.2 (پیش‌فرض‌های مستند)
    'scoring' => [
        'source_referral' => 20,
        'source_default' => 5,
        'has_company' => 15,
        'high_value_threshold' => 100_000_000,   // minor-unit
        'high_value' => 25,
        'low_value' => 10,
        'engagement_per_activity' => 5,
        'engagement_max' => 15,
        'cap' => 100,
    ],

    // SLA تیکت بر اساس اولویت (ساعت — DOC-096 §3.3)
    'sla' => [
        'urgent' => 2,
        'high' => 4,
        'normal' => 8,
        'low' => 24,
        // نقش‌هایی که escalation را دریافت می‌کنند
        'escalate_roles' => ['support-manager', 'crm-admin', 'admin'],
    ],

    // تیکت‌ها
    'tickets' => [
        'number_prefix' => 'TKT',
        'portal_enabled' => true,       // فرم عمومی /crm/support
        'auto_close_days' => 7,         // resolved → closed خودکار (۰ = خاموش)
    ],

    // کمپین‌ها
    'campaigns' => [
        'batch_size' => 50,
        'from_name' => 'پشتیبانی',
    ],

    // رمزنگاری کدملی در rest (DOC-096 §2: encrypted cast)
    'encryption' => [
        'national_id' => true,
    ],

    // اعلان‌ها
    'notifications' => [
        'ticket_assigned' => true,
        'ticket_replied' => true,
        'sla_breached' => true,
        'ticket_resolved' => true,
        'lead_assigned' => true,
        'opportunity_won' => true,
    ],

    // جست‌وجو
    'search' => [
        'index' => 'crm_contacts',
        'enabled' => true,
    ],

    // همگام‌سازی رویدادمحور (DOC-096 §7)
    'sync' => [
        'shop_orders' => true,          // shop.order.paid → upsert مخاطب
    ],

    // Theme override — توسط kit:install crm پر می‌شود
    'theme' => [
        'views_path' => null,
    ],
];
