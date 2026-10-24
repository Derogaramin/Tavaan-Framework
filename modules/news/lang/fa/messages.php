<?php

declare(strict_types=1);

/*
 * ماژول خبرگزاری — پیام‌ها و برچسب‌ها (fa). کلید: news::messages.*
 */

return [
    // عمومی
    'news_title' => 'خبرگزاری',
    'feed_title' => 'فید اخبار',
    'nav_home' => 'صفحهٔ اصلی',
    'nav_feed' => 'فید RSS',
    'powered_by' => 'نیروگرفته از فریمورک تَوان ⚡',
    'breaking' => 'خبر فوری',
    'desktop_version' => 'نسخهٔ کامل',

    // فهرست/جست‌وجو/آرشیو
    'latest_news' => 'آخرین اخبار',
    'no_news' => 'هنوز خبری منتشر نشده است.',
    'search' => 'جست‌وجو',
    'search_placeholder' => 'جست‌وجو در اخبار…',
    'search_submit' => 'جست‌وجو',
    'search_results' => 'نتایج جست‌وجو برای «:q»',
    'tag_heading' => 'برچسب: :tag',
    'archive_heading' => 'آرشیو :month',
    'categories' => 'دسته‌بندی‌ها',
    'sub_category' => 'زیردسته',
    'no_categories' => 'دسته‌بندی وجود ندارد.',
    'archive' => 'آرشیو',
    'this_month' => 'همین ماه',
    'by_author' => 'نوشتهٔ :name',
    'edited_by' => 'ویراستار: :name',
    'views' => ':count بازدید',
    'prev' => 'قبلی',
    'next' => 'بعدی',
    'related_news' => 'اخبار مرتبط',

    // نظرات
    'comments' => 'نظرات',
    'no_comments' => 'هنوز نظری ثبت نشده است.',
    'comment_form_title' => 'ثبت نظر',
    'your_name' => 'نام شما',
    'your_email' => 'ایمیل شما (منتشر نمی‌شود)',
    'comment_body' => 'متن نظر',
    'comment_submit' => 'ارسال نظر',

    // استثناها (messageId)
    'comments_disabled' => 'ثبت نظر در این پرتال غیرفعال است.',
    'comments_closed' => 'ثبت نظر برای این خبر بسته است.',
    'comments_guest_forbidden' => 'ثبت نظر فقط برای کاربران واردشده مجاز است.',
    'comment_invalid_parent' => 'نظر والد معتبر نیست.',
    'comment_not_found' => 'نظر یافت نشد.',
    'article_not_found' => 'خبر یافت نشد.',
    'category_not_found' => 'دسته یافت نشد.',
    'category_title_required' => 'عنوان دسته الزامی است.',

    // اعلان‌های workflow
    'notify_approved_subject' => 'خبر «:title» تأیید شد',
    'notify_approved_line' => 'خبر «:title» توسط ویراستار تأیید شد و در صف انتشار قرار گرفت.',
    'notify_rejected_subject' => 'خبر «:title» نیاز به بازبینی دارد',
    'notify_rejected_line' => 'خبر «:title» رد شد. دلیل: :reason',
    'notify_published_subject' => 'خبر «:title» منتشر شد',
    'notify_published_line' => 'خبر «:title» منتشر شد.',
    'notify_cta' => 'مشاهدهٔ خبر',

    // پنل مدیریت
    'admin_title' => 'مدیریت خبرگزاری',
    'admin_articles' => 'اخبار',
    'admin_categories' => 'دسته‌ها',
    'admin_comments' => 'نظرات',
    'new_article' => 'خبر جدید',
    'edit_article' => 'ویرایش خبر',
    'view_site' => 'مشاهدهٔ سایت',
    'all_statuses' => 'همهٔ وضعیت‌ها',
    'title_col' => 'عنوان',
    'status_col' => 'وضعیت',
    'author_col' => 'نویسنده',
    'date_col' => 'تاریخ',
    'views_col' => 'بازدید',
    'actions_col' => 'عملیات',
    'edit' => 'ویرایش',
    'view' => 'نمایش',
    'publish' => 'انتشار',
    'publish_now' => 'انتشار فوری',
    'delete' => 'حذف',
    'confirm_delete' => 'مطمئن هستید؟',
    'save' => 'ذخیره',
    'create' => 'ایجاد',
    'back_to_list' => 'بازگشت به فهرست',

    // فرم خبر
    'field_title' => 'عنوان',
    'field_slug' => 'slug (اختیاری)',
    'slug_auto_hint' => 'خالی بگذارید تا از عنوان ساخته شود',
    'field_category' => 'دسته',
    'no_category' => '— بدون دسته —',
    'field_lead' => 'لید خبر',
    'field_body' => 'بدنه',
    'field_tags' => 'برچسب‌ها',
    'field_featured_image' => 'تصویر شاخص (ULID رسانه)',
    'field_gallery' => 'گالری (ULIDها با کاما)',
    'seo' => 'سئو (SEO)',
    'field_seo_title' => 'عنوان سئو',
    'field_seo_description' => 'توضیح سئو',
    'field_category_title' => 'عنوان دسته',
    'field_parent' => 'دستهٔ والد',
    'field_description' => 'توضیح',
    'field_schedule_at' => 'زمان انتشار (خالی = فوری پس از انتشار سردبیر)',

    // workflow
    'workflow' => 'گردش کار',
    'submit_for_review' => 'ارسال برای بازبینی',
    'approve' => 'تأیید',
    'reject' => 'رد',
    'reject_reason' => 'دلیل رد: :reason',
    'reject_reason_placeholder' => 'دلیل رد…',
    'mark_breaking' => 'خبر فوری 🔴',
    'unmark_breaking' => 'برداشتن خبر فوری',
    'archive_action' => 'بایگانی',

    // پیام‌های عملیات
    'article_created' => '✓ خبر ساخته شد.',
    'article_updated' => '✓ خبر به‌روز شد.',
    'article_submitted' => '✓ خبر برای بازبینی ارسال شد.',
    'article_approved' => '✓ خبر تأیید شد.',
    'article_rejected' => '✓ خبر رد و به نویسنده برگشت.',
    'article_published' => '✓ خبر منتشر شد.',
    'article_scheduled' => '✓ انتشار خبر زمان‌بندی شد.',
    'article_archived' => '✓ خبر بایگانی شد.',
    'article_deleted' => '✓ خبر حذف شد.',
    'marked_breaking' => '✓ به‌عنوان خبر فوری علامت خورد.',
    'unmarked_breaking' => '✓ علامت خبر فوری برداشته شد.',
    'category_created' => '✓ دسته ساخته شد.',
    'category_updated' => '✓ دسته به‌روز شد.',
    'category_deleted' => '✓ دسته حذف شد.',
    'comment_approved' => '✓ نظر تأیید و منتشر شد.',
    'comment_marked_spam' => '✓ نظر به‌عنوان اسپم علامت خورد.',
    'comment_deleted' => '✓ نظر حذف شد.',

    // مدیریت نظرات
    'comment_author_col' => 'نویسنده',
    'comment_body_col' => 'متن',
    'comment_article_col' => 'خبر',
    'pending_count' => ':count در انتظار',
    'mark_spam' => 'اسپم',
];
