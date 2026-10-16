<?php

declare(strict_types=1);

return array (
  'disk_full' => 'فضای دیسک کافی نیست',
  'path_traversal' => 'تلاش برای دسترسی خارج از مسیر مجاز',
  'file_not_found' => 'فایل :path یافت نشد',
  'disk_not_configured' => 'دیسک :disk در config/filesystem.php پیکربندی نشده است',
  'unknown_driver' => 'درایور سامانهٔ فایل :driver ناشناخته است',
  'operation_failed' => 'عملیات :op روی :path ناموفق بود (:reason)',
  'signing_unavailable' => 'برای URL زمان‌دار، موتور crypt (APP_KEY) لازم است',
  's3_error' => 'خطای S3 در عملیات :op — کد :status',
  'transport_error' => 'خطای انتقال HTTP/FTP: :reason',
  'not_supported' => 'عملیات :op در درایور :driver پشتیبانی نمی‌شود',
);
