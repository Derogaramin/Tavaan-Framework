<?php

declare(strict_types=1);

return array (
  'manifest_invalid' => 'manifest افزونه نامعتبر است: :reason',
  'signature_missing' => 'افزونهٔ «:plugin» امضا ندارد — نصب با require_signature رد می‌شود',
  'signature_invalid' => 'امضای افزونهٔ «:plugin» معتبر نیست',
  'vendor_untrusted' => 'فروشندهٔ «:author» در trusted_vendors ثبت نشده است',
  'lock_tampered' => 'یکپارچگی فایل‌های افزونهٔ «:plugin» نقض شده است: :details',
  'lock_missing' => 'افزونهٔ «:plugin» در plugins.lock ثبت نشده است',
  'not_found' => 'افزونهٔ «:plugin» یافت نشد',
  'already_installed' => 'افزونهٔ «:plugin» پیش‌تر نصب شده است',
  'capability_denied' => 'capability «:capability» برای افزونهٔ «:plugin» مجاز نیست — :reason',
  'hook_not_declared' => 'hook «:hook» توسط میزبان اعلام نشده — افزونهٔ «:plugin» نمی‌تواند متصل شود',
  'dangerous_code' => 'کد خطرناک در افزونهٔ «:plugin» یافت شد — نیازمند تأیید صریح admin',
  'disabled' => 'سیستم افزونه غیرفعال است (TVN_PLUGINS=false)',
);
