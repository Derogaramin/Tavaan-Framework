<?php

declare(strict_types=1);

/*
 * پیکربندی ماژول کاربران پایه — modules/core-user.
 * کلید: config('core-user.*')
 */

return [
    // ثبت‌نام عمومی (فرم /register)
    'registration' => [
        'enabled' => env_bool('TVN_USER_REGISTRATION', true),
        'default_role' => env_string('TVN_USER_DEFAULT_ROLE', 'user'),   // نقش پس از ثبت‌نام
        'auto_login' => true,                                            // ورود خودکار پس از ثبت‌نام
        'require_email_verification' => false,
    ],

    // ورود
    'login' => [
        'throttle' => [env_int('TVN_USER_LOGIN_MAX', 5), env_int('TVN_USER_LOGIN_DECAY', 10)], // [تلاش، دقیقه]
        'redirect_after' => '/admin',          // مسیر پیش‌فرض پس از ورود موفق
        'field' => 'email',                    // فیلد احراز هویت (email|mobile)
    ],

    // پروفایل کاربری (/profile)
    'profile' => [
        'allow_email_change' => false,         // 🔒 تغییر ایمیل = باطل‌سازی تأیید؛ پیش‌فرض بسته
        'allow_avatar_upload' => true,
    ],

    // پنل مدیریت کاربران (/admin/users)
    'admin' => [
        'per_page' => env_int('TVN_USER_ADMIN_PER_PAGE', 20),
        'protect_last_admin' => true,          // 🔒 جلوگیری از حذف/مسدودسازی آخرین مدیر فعال
    ],
];
