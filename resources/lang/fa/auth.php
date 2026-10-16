<?php

declare(strict_types=1);

return array (
  'credentials' =>
  array (
    'invalid' => 'نام کاربری یا رمز عبور نادرست است',
  ),
  'throttled' => 'تعداد تلاش‌ها بیش از حد مجاز است؛ :retry_after ثانیه دیگر دوباره تلاش کنید',
  'account_banned' => 'این حساب کاربری مسدود شده است',
  'failed' => 'احراز هویت ناموفق بود',
  'unauthenticated' => 'برای دسترسی به این بخش باید وارد شوید',
  'token' =>
  array (
    'invalid' => 'توکن نامعتبر است',
    'malformed' => 'قالب توکن نادرست است',
    'expired' => 'توکن منقضی شده است',
    'revoked' => 'توکن باطل شده است',
    'algorithm_none' => 'الگوریتم none پذیرفته نمی‌شود',
    'algorithm_mismatch' => 'الگوریتم توکن با الگوریتم پین‌شده مطابقت ندارد',
    'algorithm_unsupported' => 'الگوریتم پشتیبانی‌نشده',
    'signature_invalid' => 'امضای توکن نامعتبر است',
    'issuer_mismatch' => 'صادرکنندهٔ توکن نامعتبر است',
    'audience_mismatch' => 'مخاطب توکن نامعتبر است',
    'not_yet_valid' => 'توکن هنوز معتبر نشده است',
    'missing_jti' => 'توکن شناسهٔ یکتا (jti) ندارد',
    'missing_subject' => 'توکن subject ندارد',
    'subject_not_found' => 'کاربر توکن یافت نشد',
    'reuse_detected' => 'استفادهٔ مجدد از توکن تازه‌سازی شناسایی شد؛ همهٔ نشست‌ها باطل شدند',
    'refresh_invalid' => 'توکن تازه‌سازی نامعتبر است',
    'refresh_expired' => 'توکن تازه‌سازی منقضی شده است',
    'key_missing' => 'کلید امضای توکن پیکربندی نشده است',
    'key_invalid' => 'کلید امضا نامعتبر است',
    'sign_failed' => 'امضای توکن ناموفق بود',
  ),
  'otp' =>
  array (
    'invalid' => 'کد یک‌بارمصرف نامعتبر یا منقضی است',
    'sent' => 'کد یک‌بارمصرف ارسال شد',
  ),
  'two_factor' =>
  array (
    'invalid' => 'کد دومرحله‌ای نامعتبر است',
  ),
  'reset' =>
  array (
    'sent' => 'لینک بازیابی رمز ارسال شد',
    'done' => 'رمز عبور بازنشانی شد',
    'invalid_token' => 'توکن بازیابی نامعتبر است',
    'expired' => 'توکن بازیابی منقضی شده است',
  ),
);
