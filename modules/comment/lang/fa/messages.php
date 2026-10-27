<?php

declare(strict_types=1);

/*
 * ماژول نظر — پیام‌ها (fa).
 */

return [
    'status' => [
        'pending' => 'در انتظار تأیید',
        'approved' => 'تأییدشده',
        'spam' => 'اسپم',
    ],
    'submitted' => 'نظر شما ثبت شد و پس از بررسی نمایش داده می‌شود.',
    'submitted_approved' => 'نظر شما ثبت و منتشر شد.',
    'closed' => 'ثبت نظر برای این محتوا بسته است.',

    // پیام‌های سرویس ثبت نظر
    'type_not_registered' => 'نوع موضوع نظرپذیر ثبت نشده است.',
    'comments_closed' => 'ثبت نظر برای این موضوع بسته است.',
    'login_required' => 'برای ثبت نظر باید وارد حساب کاربری شوید.',
    'invalid_subject' => 'شناسهٔ موضوع نامعتبر است.',
    'invalid_parent' => 'نظر والد معتبر نیست.',
    'parent_not_visible' => 'نمی‌توان به نظر تأییدنشده پاسخ داد.',
    'max_depth_reached' => 'حداکثر عمق پاسخ‌ها پر شده است.',
    'not_found' => 'نظر یافت نشد.',
    'posted_visible' => 'نظر شما ثبت و منتشر شد.',
    'posted_pending' => 'نظر شما ثبت شد و پس از بررسی نمایش داده می‌شود.',

    // پیام‌های moderation
    'approved' => 'نظر تأیید و منتشر شد.',
    'marked_spam' => 'نظر به‌عنوان اسپم علامت خورد.',
    'moved_to_pending' => 'نظر به صف بررسی بازگشت.',
    'deleted' => 'نظر حذف شد.',

    // برچسب‌های رابط کاربری
    'ui' => [
        'comments' => 'نظرات',
        'no_comments' => 'هنوز نظری ثبت نشده — اولین نفر باشید!',
        'form_title' => 'ثبت نظر جدید',
        'name' => 'نام',
        'email' => 'ایمیل',
        'body' => 'متن نظر',
        'submit' => 'ارسال نظر',
        'admin_title' => 'مدیریت نظرات',
        'dashboard' => 'پیشخوان',
        'queue' => 'صف بررسی',
        'view_site' => 'نمایش سایت',
        'stat_pending' => 'در انتظار',
        'stat_approved' => 'تأییدشده',
        'stat_spam' => 'اسپم',
        'stat_total' => 'کل نظرات',
        'all_statuses' => 'همهٔ وضعیت‌ها',
        'all_types' => 'همهٔ موضوع‌ها',
        'col_author' => 'نویسنده',
        'col_body' => 'متن',
        'col_subject' => 'موضوع',
        'col_status' => 'وضعیت',
        'col_date' => 'تاریخ',
        'col_actions' => 'عملیات',
        'approve' => 'تأیید',
        'unapprove' => 'بازگردانی',
        'spam' => 'اسپم',
        'delete' => 'حذف',
        'confirm_delete' => 'آیا از حذف این نظر مطمئن هستید؟',
        'no_comments_admin' => 'نظری یافت نشد.',
    ],
];
