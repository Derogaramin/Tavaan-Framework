<?php

declare(strict_types=1);

/*
 * ماژول محتوا — پیام‌ها و برچسب‌های صفحات CMS (fa).
 * کلیدها: content::messages.*
 */

return [
    // عمومی (فهرست/نمایش صفحات)
    'pages_title' => 'صفحات',
    'nav_home' => 'فهرست صفحات',
    'nav_sitemap' => 'نقشهٔ سایت',
    'powered_by' => 'نیروگرفته از فریمورک تَوان ⚡',
    'no_pages' => 'هنوز صفحه‌ای منتشر نشده است.',
    'views' => ':count بازدید',
    'read_more' => 'ادامهٔ مطلب',

    // استثناها (messageId)
    'page_not_found' => 'صفحه یافت نشد.',

    // نظرات (زیر صفحه — از ماژول comment)
    'comments' => 'نظرات',
    'no_comments' => 'هنوز نظری ثبت نشده است.',
    'comment_form_title' => 'ثبت نظر',
    'your_name' => 'نام شما',
    'your_email' => 'ایمیل شما (منتشر نمی‌شود)',
    'comment_body' => 'متن نظر',
    'comment_submit' => 'ارسال نظر',
    'comments_closed' => 'ثبت نظر برای این صفحه بسته است.',

    // پنل مدیریت
    'admin_title' => 'مدیریت محتوا',
    'admin_pages' => 'صفحات',
    'new_page' => 'صفحهٔ جدید',
    'edit_page' => 'ویرایش صفحه',
    'view_site' => 'مشاهدهٔ سایت',
    'all_statuses' => 'همهٔ وضعیت‌ها',
    'title_col' => 'عنوان',
    'status_col' => 'وضعیت',
    'date_col' => 'تاریخ',
    'views_col' => 'بازدید',
    'actions_col' => 'عملیات',
    'edit' => 'ویرایش',
    'publish' => 'انتشار',
    'archive_action' => 'بایگانی',
    'delete' => 'حذف',
    'confirm_delete' => 'مطمئن هستید؟',
    'save' => 'ذخیره',
    'create' => 'ایجاد',
    'back_to_list' => 'بازگشت به فهرست',
    'show_page' => 'نمایش',

    // فرم صفحه
    'field_title' => 'عنوان',
    'field_slug' => 'slug (اختیاری)',
    'slug_auto_hint' => 'خالی بگذارید تا از عنوان ساخته شود',
    'field_body' => 'بدنه',
    'body_hint' => 'Markdown پشتیبانی می‌شود؛ خروجی به‌صورت خودکار سانیتیزه می‌شود.',
    'field_excerpt' => 'خلاصه',
    'excerpt_hint' => 'خالی بگذارید تا از بدنه ساخته شود',
    'field_comments_open' => 'نظرات برای این صفحه باز باشد',
    'seo' => 'سئو (SEO)',
    'field_seo_title' => 'عنوان سئو',
    'field_seo_description' => 'توضیح سئو',
    'field_seo_keywords' => 'کلیدواژه‌ها',

    // پیام‌های عملیات
    'page_created' => '✓ صفحه ساخته شد.',
    'page_updated' => '✓ صفحه به‌روز شد.',
    'page_published' => '✓ صفحه منتشر شد.',
    'page_archived' => '✓ صفحه بایگانی شد.',
    'page_trashed' => '✓ صفحه حذف شد.',

    // ویجت داشبورد
    'widget_title' => 'صفحات',
    'widget_published' => ':count صفحهٔ منتشرشده',
    'widget_unavailable' => 'جدول صفحات آماده نیست (migrate انجام نشده).',
    'widget_link' => 'مدیریت صفحات',
];
