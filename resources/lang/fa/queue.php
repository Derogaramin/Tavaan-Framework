<?php

declare(strict_types=1);

return array (
  'unknown_driver' => 'درایور صف :driver ناشناخته است',
  'connection_not_configured' => 'اتصال صف :connection پیکربندی نشده است',
  'connection_failed' => 'اتصال صف ناموفق بود',
  'redis_connect_failed' => 'اتصال به Redis (:host::port) ناموفق بود: :error',
  'redis_write_failed' => 'نوشتن روی اتصال Redis ناموفق بود (:command)',
  'redis_read_failed' => 'خواندن پاسخ Redis ناموفق بود',
  'redis_error' => 'خطای Redis: :error',
  'redis_closed' => 'اتصال Redis بسته شده است',
  'file_write_failed' => 'نوشتن فایل صف در :dir ناموفق بود',
  'unsafe_job' => 'کلاس job در allowlist صف مجاز نیست: :class',
  'invalid_payload' => 'payload صف نامعتبر است',
  'unserializable' => 'مقدار قابل سریال‌سازی برای صف نیست: :reason',
  'max_attempts_exceeded' => 'job :job از حداکثر تلاش‌ها (:tries) گذشت',
  'job_timed_out' => 'اجرای job :job از timeout (:timeout ثانیه) گذشت',
  'database_required' => 'اتصال صف :connection به موتور database نیاز دارد',
  'no_container' => 'عملیات صف بدون Container ممکن نیست',
  'empty_batch' => 'batch بدون job قابل dispatch نیست',
  'empty_chain' => 'chain بدون job قابل dispatch نیست',
);
