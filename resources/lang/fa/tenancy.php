<?php

declare(strict_types=1);

return array (
  'not_found' => 'tenant یافت نشد',
  'suspended' => 'tenant غیرفعال یا معلق است',
  'quota_exceeded' => 'سهمیهٔ «:resource» به پایان رسید (حد :limit، مصرف :used)',
  'context_missing' => 'زمینهٔ tenant مشخص نیست — عملیات tenant-aware بدون TenantContext ممکن نیست',
  'invalid_identifier' => 'شناسهٔ tenant «:identifier» نامعتبر است (فقط حروف/عدد/._- حداکثر ۶۴ کاراکتر)',
  'duplicate_identifier' => 'tenant با شناسهٔ «:identifier» پیش‌تر ثبت شده است',
  'create_failed' => 'ایجاد tenant «:identifier» ناموفق بود',
  'database_required' => 'عملیات چنداجاره‌ای به اتصال database نیاز دارد',
  'template_missing' => 'قالب اتصال tenant «:template» در config/database.php وجود ندارد',
  'provision_failed' => 'ساخت فضای دادهٔ tenant ناموفق بود: :reason',
  'unsafe_identifier' => 'شناسهٔ ناامن برای عملیات پایگاه‌داده رد شد: :identifier',
  'schema_requires_pgsql' => 'استراتژی schema فقط روی PostgreSQL کار می‌کند (درایور جاری: :driver)',
  'console_required' => 'اجرای migration به موتور console نیاز دارد',
  'cross_tenant_forbidden' => 'عملیات cross-tenant بدون مجوز tenancy.cross_tenant ممنوع است',
);
