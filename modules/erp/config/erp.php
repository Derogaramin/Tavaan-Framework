<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول ERP — modules/erp (DOC-097).
 * کلید: config('erp.*')
 */

return [
    // پول — ADR-0013 (int minor-unit؛ IRR → ریال)
    'currency' => 'IRR',

    'per_page' => 20,

    // ── حسابداری ──────────────────────────────────────────────────────────
    'accounting' => [
        // نقشهٔ حساب‌های سیستمی (کد → شناسه در chart of accounts seed)
        // اسناد خودکار با این کدها صادر می‌شوند.
        'accounts' => [
            'cash' => '1101',
            'bank' => '1102',
            'receivable' => '1201',
            'inventory' => '1301',          // کالای تجاری
            'raw_material' => '1302',        // مواد اولیه (تولید)
            'wip' => '1303',                 // کالای در جریان ساخت
            'finished_goods' => '1304',      // محصول ساخته‌شده
            'payable' => '2101',             // حساب‌های پرداختنی تجاری
            'salary_payable' => '2201',
            'capital' => '3101',
            'income_summary' => '3201',      // خلاصهٔ سود و زیان (بستن دوره)
            'retained_earnings' => '3301',
            'revenue' => '4101',
            'cogs' => '5101',                // بهای کالای فروش‌رفته
            'salary_expense' => '5201',
            'other_expense' => '5301',
        ],
        // اسناد سیستمی (رخدادها) بلافاصله posted شوند؟ (draft = نیازمند تأیید دستی)
        'auto_post_system_vouchers' => true,
        // شماره‌گذاری سند: <prefix>-<period>-<seq>
        'voucher_prefix' => 'SV',
    ],

    // ── تدارکات — تأیید سلسله‌مراتبی (DOC-097 §3) ─────────────────────────
    'procurement' => [
        'workflow' => 'erp-purchase-approval',
        // خرید ≤ finance_threshold → تأیید خودکار
        // > finance_threshold → مدیر مالی
        // > ceo_threshold → مدیرعامل (پس از مدیر مالی)
        'finance_threshold' => 100_000_000,   // minor-unit (۱۰۰ میلیون ریال — مستند §3)
        'ceo_threshold' => 500_000_000,
    ],

    // ── انبار ─────────────────────────────────────────────────────────────
    'inventory' => [
        'valuation_method' => 'weighted_avg',   // fifo|weighted_avg (lifo در بک‌لاگ)
        'low_stock_alert' => true,
    ],

    // ── حقوق و دستمزد ─────────────────────────────────────────────────────
    'payroll' => [
        'overtime_rate' => 1.4,        // ضریب اضافه‌کاری (ساعتی = base/220 × rate)
        'monthly_hours' => 220,
        'tax_rate' => 0.0,             // مالیات حقوق (پیش‌فرض خاموش — سازمانی config می‌کند)
        'tax_threshold' => 0,          // سقف معافیت (minor)
    ],

    // ── یکپارچگی — Outbox (ADR-0009 / AC-ERP-11) ──────────────────────────
    'integration' => [
        'outbox_table' => 'tvn_outbox',
        'inline_relay' => true,        // پس از commit، relay درون‌فرایندی (best-effort؛ production می‌تواند به event:outbox-relay زمان‌بندی‌شده بسپارد)
    ],

    // ── Audit (DOC-097 §7) ────────────────────────────────────────────────
    'audit' => [
        'enabled' => true,             // رویدادهای مالی → موتور audit (hash chain)
    ],

    // اعلان‌ها (سبک — فقط پایگاه/ایمیل مدیریتی)
    'notifications' => [
        'po_needs_approval' => true,
        'low_stock' => true,
    ],

    // Theme override — توسط kit:install erp پر می‌شود
    'theme' => [
        'views_path' => null,
    ],
];
