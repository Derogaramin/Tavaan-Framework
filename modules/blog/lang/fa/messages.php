<?php

declare(strict_types=1);

/*
 * ماژول وبلاگ — پیام‌ها و برچسب‌ها (fa).
 * کلیدها: blog::messages.*
 */

return [
    // عمومی
    'blog_title' => 'وبلاگ',
    'feed_title' => 'فید وبلاگ',
    'nav_home' => 'خانهٔ وبلاگ',
    'nav_feed' => 'فید RSS',
    'powered_by' => 'نیروگرفته از فریمورک تَوان ⚡',

    // فهرست/جست‌وجو/آرشیو
    'latest_posts' => 'آخرین مقالات',
    'no_posts' => 'هنوز مقاله‌ای منتشر نشده است.',
    'search' => 'جست‌وجو',
    'search_placeholder' => 'عبارت فارسی یا انگلیسی…',
    'search_submit' => 'جست‌وجو',
    'search_results' => 'نتایج جست‌وجو برای «:q»',
    'search_count' => ':count نتیجه یافت شد',
    'tag_heading' => 'برچسب: :tag',
    'archive_heading' => 'آرشیو :month',
    'categories' => 'دسته‌بندی‌ها',
    'no_categories' => 'دسته‌بندی وجود ندارد.',
    'archive' => 'آرشیو',
    'this_month' => 'همین ماه',
    'by_author' => 'نوشتهٔ :name',
    'views' => ':count بازدید',
    'prev' => 'قبلی',
    'next' => 'بعدی',

    // نظرات (نمایش عمومی)
    'comments' => 'نظرات',
    'no_comments' => 'هنوز نظری ثبت نشده است.',
    'comment_form_title' => 'ثبت نظر',
    'your_name' => 'نام شما',
    'your_email' => 'ایمیل شما (منتشر نمی‌شود)',
    'comment_body' => 'متن نظر',
    'comment_submit' => 'ارسال نظر',

    // استثناها (messageId)
    'comments_disabled' => 'ثبت نظر در این وبلاگ غیرفعال است.',
    'comments_closed' => 'ثبت نظر برای این مقاله بسته است.',
    'comments_guest_forbidden' => 'ثبت نظر فقط برای کاربران واردشده مجاز است.',
    'comment_invalid_parent' => 'نظر والد معتبر نیست.',
    'comment_not_found' => 'نظر یافت نشد.',
    'post_not_found' => 'مقاله یافت نشد.',

    // اعلان‌ها
    'notify_author_subject' => 'نظر جدید برای «:title»',
    'notify_author_line' => ':name نظر جدیدی روی مقالهٔ «:title» ثبت کرد.',
    'notify_author_cta' => 'مشاهده و بررسی',
    'notify_commenter_subject' => 'نظر شما منتشر شد',
    'notify_commenter_greeting' => 'سلام :name،',
    'notify_commenter_line' => 'نظر شما پس از بررسی تأیید و منتشر شد.',
    'notify_commenter_cta' => 'مشاهدهٔ نظر',

    // پنل مدیریت
    'admin_title' => 'مدیریت وبلاگ',
    'admin_posts' => 'مقالات',
    'admin_comments' => 'نظرات',
    'new_post' => 'مقالهٔ جدید',
    'edit_post' => 'ویرایش مقاله',
    'view_site' => 'مشاهدهٔ سایت',
    'all_statuses' => 'همهٔ وضعیت‌ها',
    'title_col' => 'عنوان',
    'status_col' => 'وضعیت',
    'author_col' => 'نویسنده',
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

    // فرم مقاله
    'field_title' => 'عنوان',
    'field_slug' => 'slug (اختیاری)',
    'slug_auto_hint' => 'خالی بگذارید تا از عنوان ساخته شود',
    'field_category' => 'دسته',
    'no_category' => '— بدون دسته —',
    'field_body' => 'بدنه',
    'body_hint' => 'Markdown پشتیبانی می‌شود؛ خروجی به‌صورت خودکار سانیتیزه می‌شود.',
    'field_excerpt' => 'خلاصه',
    'field_tags' => 'برچسب‌ها',
    'tags_hint' => 'با «،» جدا کنید',
    'field_featured_image' => 'تصویر شاخص (ULID رسانه)',
    'field_status' => 'وضعیت',
    'field_publish_at' => 'زمان انتشار',
    'schedule_hint' => 'پر کنید → زمان‌بندی؛ خالی → انتشار فوری',
    'seo' => 'سئو (SEO)',
    'field_seo_title' => 'عنوان سئو',
    'field_seo_description' => 'توضیح سئو',
    'field_seo_keywords' => 'کلیدواژه‌ها',

    // پیام‌های عملیات
    'post_created' => '✓ مقاله ساخته شد.',
    'post_updated' => '✓ مقاله به‌روز شد.',
    'post_published' => '✓ مقاله منتشر شد.',
    'post_scheduled' => '✓ انتشار مقاله زمان‌بندی شد.',
    'post_archived' => '✓ مقاله بایگانی شد.',
    'post_deleted' => '✓ مقاله حذف شد.',
    'comment_approved' => '✓ نظر تأیید و منتشر شد.',
    'comment_marked_spam' => '✓ نظر به‌عنوان اسپم علامت خورد.',
    'comment_deleted' => '✓ نظر حذف شد.',

    // مدیریت نظرات
    'comment_author_col' => 'نویسنده',
    'comment_body_col' => 'متن',
    'comment_post_col' => 'مقاله',
    'pending_count' => ':count در انتظار',
    'approve' => 'تأیید',
    'mark_spam' => 'اسپم',
];
