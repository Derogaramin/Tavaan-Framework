<?php

declare(strict_types=1);

/*
 * ماژول نظرسنجی — پیام‌ها و برچسب‌ها (fa).
 * کلیدها: poll::messages.*
 */

return [
    // عمومی
    'polls_title' => 'نظرسنجی‌ها',
    'nav_home' => 'فهرست نظرسنجی‌ها',
    'powered_by' => 'نیروگرفته از فریمورک تَوان ⚡',
    'no_polls' => 'اکنون نظرسنجی بازی وجود ندارد.',
    'votes_count' => ':count رأی',
    'ends_at_label' => 'پایان رأی‌گیری: :date',
    'starts_at_label' => 'شروع رأی‌گیری: :date',
    'vote_submit' => 'ثبت رأی',
    'multiple_hint' => 'می‌توانید چند گزینه را انتخاب کنید.',
    'results_title' => 'نتایج',
    'total_votes' => 'مجموع رأی‌ها: :count',
    'you_voted' => 'رأی شما ثبت شده است ✓',
    'closed_notice' => 'رأی‌گیری این نظرسنجی بسته شده است.',
    'not_started_notice' => 'رأی‌گیری این نظرسنجی هنوز آغاز نشده است.',
    'option_col' => 'گزینه',
    'count_col' => 'شمار',
    'percent_col' => 'درصد',

    // استثناها (messageId)
    'poll_not_found' => 'نظرسنجی یافت نشد.',
    'not_votable' => 'این نظرسنجی اکنون رأی‌پذیر نیست.',
    'login_required' => 'رأی‌دادن فقط برای کاربران واردشده مجاز است.',
    'option_required' => 'دست‌کم یک گزینهٔ معتبر انتخاب کنید.',
    'single_choice_only' => 'این نظرسنجی تک‌انتخابی است.',
    'already_voted' => 'شما پیش‌تر در این نظرسنجی رأی داده‌اید.',
    'options_required' => 'دست‌کم دو گزینه وارد کنید.',
    'options_count' => 'تعداد گزینه‌ها نامعتبر است.',
    'options_locked' => 'گزینه‌ها پس از نخستین رأی قابل تغییر نیستند.',
    'window_invalid' => 'پایان رأی‌گیری باید پس از شروع آن باشد.',
    'date_invalid' => 'قالب تاریخ نامعتبر است.',
    'vote_ok' => '✓ رأی شما ثبت شد.',

    // پنل مدیریت
    'admin_title' => 'مدیریت نظرسنجی',
    'admin_polls' => 'نظرسنجی‌ها',
    'new_poll' => 'نظرسنجی جدید',
    'edit_poll' => 'ویرایش نظرسنجی',
    'view_site' => 'مشاهدهٔ سایت',
    'all_statuses' => 'همهٔ وضعیت‌ها',
    'question_col' => 'پرسش',
    'status_col' => 'وضعیت',
    'votes_col' => 'رأی‌ها',
    'date_col' => 'تاریخ',
    'actions_col' => 'عملیات',
    'edit' => 'ویرایش',
    'open_action' => 'بازکردن',
    'close_action' => 'بستن',
    'delete' => 'حذف',
    'confirm_delete' => 'مطمئن هستید؟',
    'save' => 'ذخیره',
    'create' => 'ایجاد',
    'back_to_list' => 'بازگشت به فهرست',
    'show_poll' => 'نمایش',

    // فرم
    'field_question' => 'پرسش',
    'field_slug' => 'slug (اختیاری)',
    'slug_auto_hint' => 'خالی بگذارید تا از پرسش ساخته شود',
    'field_options' => 'گزینه‌ها',
    'option_n' => 'گزینهٔ :n',
    'field_multiple' => 'رأی چندگانه (چند گزینه هم‌زمان)',
    'field_starts_at' => 'شروع رأی‌گیری',
    'field_ends_at' => 'پایان رأی‌گیری',
    'window_hint' => 'خالی → بدون محدودیت زمانی',
    'field_status' => 'وضعیت',
    'options_locked_hint' => 'این نظرسنجی رأی دارد — گزینه‌ها قفل هستند.',

    // پیام‌های عملیات
    'poll_created' => '✓ نظرسنجی ساخته شد.',
    'poll_updated' => '✓ نظرسنجی به‌روز شد.',
    'poll_opened' => '✓ رأی‌گیری باز شد.',
    'poll_closed' => '✓ رأی‌گیری بسته شد.',
    'poll_trashed' => '✓ نظرسنجی حذف شد.',

    // ویجت داشبورد
    'widget_title' => 'نظرسنجی',
    'widget_unavailable' => 'جدول نظرسنجی آماده نیست (migrate انجام نشده).',
    'widget_link' => 'مدیریت نظرسنجی‌ها',
];
