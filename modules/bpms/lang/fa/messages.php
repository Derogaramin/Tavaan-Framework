<?php

declare(strict_types=1);

/*
 * ماژول BPMS — پیام‌های فارسی (DOC-098).
 */

return [
    // ── عنوان‌ها ──────────────────────────────────────────────────────────
    'bpms_title' => 'مدیریت فرایندهای کسب‌وکار',
    'monitoring' => 'پایش فرایندها',
    'definitions' => 'فرایندها (تعاریف)',
    'instances' => 'نمونه‌های فرایند',
    'my_tasks' => 'کارهای من',
    'processes' => 'فرایندها',
    'audit' => 'ردپای Audit',
    'tasks' => 'کارها',
    'search' => 'جست‌وجو',
    'actions' => 'عملیات',
    'status' => 'وضعیت',
    'version' => 'نسخه',
    'back' => 'بازگشت',
    'save' => 'ذخیره',
    'cancel' => 'انصراف',
    'prev_page' => 'قبلی',
    'next_page' => 'بعدی',
    'no_results' => 'موردی یافت نشد.',

    // ── تعریف فرایند ──────────────────────────────────────────────────────
    'definition_not_found' => 'تعریف فرایند یافت نشد.',
    'definition_name_invalid' => 'نام فرایند خالی یا بیش از حد طولانی است.',
    'definition_key_invalid' => 'کلید فرایند نامعتبر است (فقط حروف کوچک، رقم، - و _).',
    'definition_json_invalid' => 'JSON تعریف فرایند نامعتبر است.',
    'form_json_invalid' => 'JSON فرم‌ها نامعتبر است.',
    'form_invalid' => 'ساختار فرم نامعتبر است.',
    'draft' => 'پیش‌نویس',
    'published' => 'منتشرشده',
    'retired' => 'بازنشسته',
    'active' => 'فعال',
    'inactive' => 'غیرفعال',
    'publish' => 'انتشار',
    'activate' => 'فعال‌سازی',
    'retire' => 'بازنشستگی',
    'new_definition' => 'فرایند جدید',
    'edit_definition' => 'ویرایش فرایند',
    'diagram' => 'نمودار فرایند',
    'mermaid_source' => 'منبع Mermaid',

    // ── نمونه ─────────────────────────────────────────────────────────────
    'instance_not_found' => 'نمونهٔ فرایند یافت نشد.',
    'running' => 'در جریان',
    'completed' => 'تکمیل‌شده',
    'suspended' => 'معلق',
    'terminated' => 'متوقف‌شده',
    'start_process' => 'آغاز فرایند',
    'business_key' => 'کلید کسب‌وکار',
    'variables' => 'متغیرها (JSON)',
    'initiator' => 'آغازگر',
    'current_state' => 'وضعیت فعلی',
    'timeline' => 'تاریخچه',
    'terminate' => 'پایان اجباری',
    'suspend' => 'تعلیق',
    'resume' => 'ادامه',

    // ── کار ───────────────────────────────────────────────────────────────
    'task_not_found' => 'کار یافت نشد.',
    'task_pending' => 'در صف',
    'task_claimed' => 'تصاحب‌شده',
    'task_completed' => 'تکمیل‌شده',
    'task_canceled' => 'لغوشده',
    'claim' => 'تصاحب',
    'complete' => 'تکمیل',
    'delegate' => 'واگذاری',
    'due_at' => 'سررسید',
    'escalated' => 'escalate شده',
    'overdue' => 'سررسیدگذشته',
    'comment' => 'یادداشت',
    'claimed_tasks' => 'کارهای تصاحب‌شدهٔ من',
    'available_tasks' => 'کارهای قابل تصاحب',
    'assignee' => 'انجام‌دهنده',

    // ── پایش ──────────────────────────────────────────────────────────────
    'active_instances' => 'نمونه‌های فعال',
    'completed_instances' => 'تکمیل‌شده',
    'avg_cycle' => 'میانگین چرخه',
    'bottlenecks' => 'گلوگاه‌ها',
    'sla_compliance' => 'انطباق SLA',
    'workload' => 'بار کاری',
    'recent_activity' => 'فعالیت اخیر',
    'all_definitions' => 'همهٔ فرایندها',
    'download_csv' => 'خروجی CSV',

    // ── امنیت ─────────────────────────────────────────────────────────────
    'forbidden' => 'شما مجوز این عملیات را ندارید.',

    // ── اعلان‌ها ──────────────────────────────────────────────────────────
    'notify_task_subject' => 'کار جدید: :title',
    'notify_task_line' => 'کار «:title» در انتظار اقدام شماست.',
    'notify_task_cta' => 'مشاهدهٔ کار',
    'notify_completed_subject' => 'فرایند تکمیل شد: :key',
    'notify_completed_line' => 'فرایند «:key» به پایان رسید.',
    'notify_completed_cta' => 'مشاهدهٔ نمونه',
    'notify_escalated_subject' => 'کار سررسیدگذشته: :title',
    'notify_escalated_line' => 'کار «:title» از سررسید گذشته و escalate شد.',
];
