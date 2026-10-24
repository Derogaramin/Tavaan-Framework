<?php

declare(strict_types=1);

/*
 * ماژول انجمن — پیام‌ها و برچسب‌ها (fa). کلید: forum::messages.*
 */

return [
    // عمومی
    'forum_title' => 'انجمن',
    'nav_forums' => 'تالارها',
    'nav_search' => 'جست‌وجو',
    'nav_profile' => 'پروفایل من',
    'nav_login' => 'ورود',
    'powered_by' => 'نیروگرفته از فریمورک تَوان ⚡',
    'guest' => 'مهمان',

    // فهرست تالارها
    'forums' => 'تالارها',
    'no_forums' => 'هنوز تالاری ساخته نشده است.',
    'sub_forums' => 'زیرتالارها',
    'threads' => 'موضوعات',
    'posts' => 'پست‌ها',
    'no_threads' => 'موضوعی وجود ندارد.',
    'latest_threads' => 'آخرین موضوعات',
    'stats' => 'آمار انجمن',
    'stat_forums' => 'تالارها: :n',
    'stat_threads' => 'موضوعات: :n',
    'stat_posts' => 'پست‌ها: :n',
    'stat_members' => 'اعضا: :n',
    'top_members' => 'اعضای برتر',
    'no_members' => 'هنوز عضوی ثبت نشده است.',
    'replies' => 'پاسخ‌ها',
    'views' => 'بازدید',
    'pinned' => 'سنجاق',
    'locked' => 'قفل',
    'open' => 'باز',
    'solved' => 'حل‌شده',

    // جست‌وجو
    'search' => 'جست‌وجو',
    'search_placeholder' => 'جست‌وجو در موضوعات و پست‌ها…',
    'search_submit' => 'جست‌وجو',
    'search_results' => 'نتایج جست‌وجو',
    'no_results' => 'نتیجه‌ای یافت نشد.',

    // موضوع
    'new_thread' => 'موضوع جدید',
    'forum_label' => 'تالار',
    'thread_title' => 'عنوان موضوع',
    'thread_body' => 'متن موضوع',
    'thread_submit' => 'انتشار موضوع',
    'thread_starter' => 'شروع‌کننده',
    'forum_locked_notice' => 'این تالار قفل است و موضوع جدید نمی‌پذیرد.',
    'thread_locked_notice' => 'این موضوع قفل است و پاسخ جدید نمی‌پذیرد.',
    'cannot_reply' => 'شما اجازهٔ ارسال پاسخ ندارید.',
    'login_to_reply' => 'برای ارسال پاسخ وارد شوید:',

    // پست/پاسخ
    'reply' => 'پاسخ',
    'reply_body' => 'متن پاسخ',
    'reply_submit' => 'ارسال پاسخ',
    'no_posts' => 'پستی وجود ندارد.',
    'quote' => 'نقل‌قول',
    'edit' => 'ویرایش',
    'edit_post' => 'ویرایش پست',
    'save' => 'ذخیره',
    'cancel' => 'انصراف',
    'delete' => 'حذف',
    'delete_confirm' => 'مطمئنید؟ این عملیات قابل بازگشت نیست.',
    'hide' => 'پنهان',
    'unhide' => 'آشکارسازی',
    'edited' => 'ویرایش‌شده',
    'post_hidden_notice' => 'این پست توسط moderator پنهان شده است.',
    'markdown_hint' => 'Markdown پشتیبانی می‌شود: **پررنگ**، `کد`، > نقل‌قول، [لینک](https://…) و @username',

    // رأی
    'upvote' => 'رأی مثبت',
    'downvote' => 'رأی منفی',
    'vote' => 'رأی',

    // پاسخ برگزیده
    'solution' => 'پاسخ برگزیده',
    'mark_solution' => '✅ انتخاب به‌عنوان پاسخ برگزیده',
    'unmark_solution' => 'برداشتن پاسخ برگزیده',

    // flag / moderation
    'flag' => 'گزارش',
    'flag_reason_prompt' => 'دلیل گزارش (اختیاری):',
    'admin_moderation' => 'صف Moderation',
    'flag_pending' => 'در انتظار بررسی',
    'flag_reporter' => 'گزارش‌دهنده',
    'flag_author' => 'نویسندهٔ پست',
    'resolve_hide' => '✓ اقدام شد (پنهان)',
    'dismiss' => 'رد گزارش',
    'queue_empty' => 'صف moderation خالی است. 🎉',
    'view_in_thread' => 'مشاهده در موضوع',
    'post_removed' => 'پست گزارش‌شده در دسترس نیست.',
    'status_visible' => 'آشکار',
    'status_hidden' => 'پنهان',
    'status_deleted' => 'حذف‌شده',

    // اشتراک
    'subscribe' => '🔔 دنبال‌کردن',
    'unsubscribe' => 'لغو دنبال‌کردن',

    // realtime
    'live_on' => 'زنده',
    'live_off' => 'آفلاین',
    'live_new_post' => '🔔 پاسخ جدیدی رسید — برای مشاهده صفحه را تازه کنید',

    // پروفایل/عضو
    'karma' => 'امتیاز',
    'username' => 'نام کاربری',
    'username_hint' => '۳ تا ۳۰ نویسه — حرف/رقم/_/. (برای @mention)',
    'bio' => 'بیوگرافی',
    'signature' => 'امضا',
    'edit_profile' => 'ویرایش پروفایل',
    'member_since' => 'عضو از',
    'recent_threads' => 'آخرین موضوعات',
    'stat_threads_label' => 'موضوعات',
    'stat_posts_label' => 'پست‌ها',
    'stat_solutions_label' => 'پاسخ‌های برگزیده',

    // ban
    'ban_author' => '🚫 ban نویسنده',
    'ban_days' => 'روز (۰ = دائم)',
    'ban_confirm' => 'کاربر ban شود؟',

    // پنل مدیریت
    'admin_title' => 'مدیریت انجمن',
    'admin_forums' => 'تالارها',
    'admin_threads' => 'موضوعات',
    'view_site' => 'مشاهدهٔ انجمن',
    'new_forum' => 'تالار جدید',
    'edit_forum' => 'ویرایش تالار',
    'title' => 'عنوان',
    'slug' => 'slug',
    'slug_optional' => 'اختیاری — از عنوان ساخته می‌شود',
    'description' => 'توضیح',
    'parent_forum' => 'تالار والد',
    'no_parent' => '— بدون والد (ریشه) —',
    'position' => 'ترتیب',
    'status' => 'وضعیت',
    'actions' => 'عملیات',
    'all_forums' => 'همهٔ تالارها',
    'lock' => 'قفل',
    'unlock' => 'بازکردن',
    'pin' => 'سنجاق',
    'unpin' => 'برداشتن سنجاق',
    'move' => 'جابجایی',
    'invalid_title' => 'عنوان نامعتبر است.',

    // پیام‌های فلش
    'thread_created' => 'موضوع شما منتشر شد. ✅',
    'post_created' => 'پاسخ شما ثبت شد. ✅',
    'post_updated' => 'پست ویرایش شد.',
    'subscribed' => 'موضوع دنبال می‌شود. 🔔',
    'unsubscribed' => 'دنبال‌کردن لغو شد.',
    'solution_marked' => 'پاسخ برگزیده ثبت شد. ✅',
    'solution_unmarked' => 'پاسخ برگزیده برداشته شد.',
    'flagged' => 'گزارش شما ثبت شد و در صف moderation قرار گرفت. 🚩',
    'profile_updated' => 'پروفایل به‌روزرسانی شد.',
    'forum_created' => 'تالار ساخته شد.',
    'forum_updated' => 'تالار ویرایش شد.',
    'forum_locked' => 'تالار قفل شد.',
    'forum_unlocked' => 'قفل تالار باز شد.',
    'forum_deleted' => 'تالار حذف شد.',
    'thread_locked' => 'موضوع قفل شد.',
    'thread_unlocked' => 'قفل موضوع باز شد.',
    'thread_pin_toggled' => 'وضعیت سنجاق تغییر کرد.',
    'thread_moved' => 'موضوع جابجا شد.',
    'thread_renamed' => 'عنوان موضوع ویرایش شد.',
    'thread_deleted' => 'موضوع و همهٔ پست‌هایش حذف شد.',
    'flag_resolved' => 'گزارش بررسی و پست پنهان شد.',
    'flag_dismissed' => 'گزارش رد شد.',
    'post_hidden' => 'پست پنهان شد.',
    'post_unhidden' => 'پست آشکار شد.',
    'post_deleted' => 'پست حذف شد.',
    'user_banned' => 'کاربر در انجمن ban شد.',
    'user_unbanned' => 'ban کاربر لغو شد.',

    // اعلان‌ها
    'notify_cta' => 'مشاهده در انجمن',
    'notify_reply_subject' => 'پاسخ جدید: :thread',
    'notify_reply_line' => ':user به موضوع پاسخ داد: «:excerpt»',
    'notify_mention_subject' => 'از شما نام برده شد: :thread',
    'notify_mention_line' => ':user در انجمن از شما نام برد.',
    'notify_solution_subject' => 'پاسخ شما برگزیده شد ✅',
    'notify_solution_line' => 'پاسخ شما در موضوع «:thread» به‌عنوان پاسخ برگزیده انتخاب شد.',
    'notify_flag_subject' => 'گزارش جدید در صف moderation 🚩',
    'notify_flag_line' => 'پستی گزارش شد. دلیل: :reason',
    'notify_thread_subject' => 'موضوع جدید در انجمن',
    'notify_thread_line' => 'موضوع تازه: «:title»',
];
