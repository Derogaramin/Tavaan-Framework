<?php

declare(strict_types=1);

/*
 * ماژول کاربران پایه — پیام‌ها (fa).
 */

return [
    // خطاهای احراز هویت
    'email_taken' => 'این ایمیل پیش‌تر ثبت شده است.',
    'create_failed' => 'ساخت کاربر ناموفق بود.',
    'credentials_required' => 'ایمیل و رمز عبور الزامی است.',
    'invalid_credentials' => 'ایمیل یا رمز عبور نادرست است.',
    'account_banned' => 'این حساب کاربری مسدود شده است.',
    'account_inactive' => 'این حساب کاربری غیرفعال است.',
    'logged_out' => 'با موفقیت خارج شدید.',
    'registered' => 'ثبت‌نام با موفقیت انجام شد. خوش آمدید!',
    'registration_disabled' => 'ثبت‌نام عمومی غیرفعال است.',
    'wrong_current_password' => 'رمز عبور جاری نادرست است.',

    // مدیریت کاربران
    'user_not_found' => 'کاربر یافت نشد.',
    'user_created' => 'کاربر با موفقیت ساخته شد.',
    'user_updated' => 'کاربر با موفقیت به‌روزرسانی شد.',
    'user_deleted' => 'کاربر حذف شد.',
    'status_changed' => 'وضعیت کاربر تغییر کرد.',
    'password_reset' => 'رمز عبور بازنشانی شد.',
    'password_changed' => 'رمز عبور تغییر کرد.',
    'profile_updated' => 'پروفایل به‌روزرسانی شد.',
    'invalid_status' => 'وضعیت نامعتبر است.',
    'cannot_delete_self' => 'نمی‌توانید حساب کاربری خود را حذف کنید.',
    'cannot_change_self_status' => 'نمی‌توانید وضعیت حساب خود را تغییر دهید.',
    'last_admin_protected' => 'آخرین مدیر فعال را نمی‌توان حذف یا غیرفعال کرد.',

    // وضعیت‌ها
    'status' => [
        'active' => 'فعال',
        'inactive' => 'غیرفعال',
        'banned' => 'مسدود',
    ],

    // برچسب‌های رابط کاربری
    'ui' => [
        'app_name' => 'تَوان',
        'login_title' => 'ورود به حساب کاربری',
        'register_title' => 'ایجاد حساب کاربری',
        'profile_title' => 'پروفایل کاربری',
        'admin_title' => 'مدیریت کاربران',
        'email' => 'ایمیل',
        'password' => 'رمز عبور',
        'current_password' => 'رمز عبور جاری',
        'new_password' => 'رمز عبور جدید',
        'confirm_password' => 'تکرار رمز عبور',
        'name' => 'نام و نام خانوادگی',
        'mobile' => 'موبایل',
        'bio' => 'بیوگرافی',
        'status' => 'وضعیت',
        'roles' => 'نقش‌ها',
        'remember_me' => 'مرا به خاطر بسپار',
        'login' => 'ورود',
        'logout' => 'خروج',
        'register' => 'ثبت‌نام',
        'no_account' => 'حساب کاربری ندارید؟',
        'have_account' => 'حساب کاربری دارید؟',
        'save' => 'ذخیره',
        'create_user' => 'ساخت کاربر',
        'edit_user' => 'ویرایش کاربر',
        'new_user' => 'کاربر جدید',
        'search_placeholder' => 'جست‌وجو در نام/ایمیل/موبایل…',
        'all_statuses' => 'همهٔ وضعیت‌ها',
        'col_name' => 'نام',
        'col_email' => 'ایمیل',
        'col_mobile' => 'موبایل',
        'col_status' => 'وضعیت',
        'col_created' => 'تاریخ عضویت',
        'col_last_login' => 'آخرین ورود',
        'col_actions' => 'عملیات',
        'edit' => 'ویرایش',
        'delete' => 'حذف',
        'confirm_delete' => 'آیا از حذف این کاربر مطمئن هستید؟',
        'activate' => 'فعال‌سازی',
        'deactivate' => 'غیرفعال‌سازی',
        'ban' => 'مسدودسازی',
        'reset_password' => 'بازنشانی رمز',
        'reset_password_hint' => 'رمز جدید (حداقل ۸ نویسه):',
        'view_site' => 'نمایش سایت',
        'dashboard' => 'پیشخوان',
        'my_profile' => 'پروفایل من',
        'users_count' => 'کاربران',
        'no_users' => 'کاربری یافت نشد.',
        'you' => '(شما)',
        'change_password' => 'تغییر رمز عبور',
        'password_hint' => 'حداقل ۸ نویسه.',
        'created_at' => 'تاریخ عضویت',
        'page' => 'صفحه',
    ],
];
