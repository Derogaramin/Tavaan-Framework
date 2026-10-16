<?php

declare(strict_types=1);

return array (
  'connection_failed' => 'اتصال HTTP به :url ناموفق بود: :reason',
  'too_many_redirects' => 'حداکثر تعداد redirect برای :url رد شد',
  'response_too_large' => 'پاسخ :url از سقف مجاز (:max بایت) بزرگ‌تر است',
  'request_failed' => 'درخواست HTTP به :url با کد :status شکست خورد',
  'circuit_open' => 'مدار سرویس :service باز است (cooldown: :cooldown ثانیه)',
  'invalid_url' => 'URL نامعتبر: :url',
  'ssrf_blocked' => 'درخواست به :url توسط محافظ SSRF مسدود شد (:reason)',
  'dns_failed' => 'resolve نام دامنهٔ :host ناموفق بود',
  'assertion_failed' => 'assertion کلاینت HTTP ناموفق بود (:assert)',
);
