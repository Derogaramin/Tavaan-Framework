<?php

declare(strict_types=1);

return array (
  'unknown_driver' => 'درایور جست‌وجوی :driver ناشناخته است',
  'invalid_index_name' => 'نام ایندکس «:name» نامعتبر است (فقط حروف/عدد/_/-، حداکثر ۶۴ کاراکتر)',
  'driver_unavailable' => 'درایور جست‌وجوی :driver در دسترس نیست: :reason',
  'query_too_long' => 'کوئری جست‌وجو از حداکثر طول مجاز (:max) بلندتر است',
  'connection_required' => 'درایور جست‌وجو به اتصال database نیاز دارد: :driver',
  'persist_failed' => 'ذخیرهٔ ایندکس در :path ناموفق بود: :reason',
  'field_not_allowed' => 'فیلد «:field» برای ایندکس «:index» مجاز نیست',
  'unknown_filter_op' => 'عملگر فیلتر «:op» ناشناخته است',
  'model_not_configured' => 'برای ایندکس «:index» هیچ Model در config/search.php تعریف نشده است',
  'missing_doc_id' => 'شناسهٔ سند برای ایندکس‌گذاری مشخص نیست (id یا docId لازم است)',
  'invalid_document' => 'سند جست‌وجو باید Model یا array باشد',
);
