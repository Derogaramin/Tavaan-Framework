# DOC-133 — راهنمای استقرار و Hardening تولید 🛡️

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-133 |
| نقش | استقرار production + hardening (پاسخ به نقد §3.3 و ADR-0017) |
| مرتبط | DOC-004 (Quick Start)، DOC-112 (کارایی)، DOC-132 (مهاجرت)، ADR-0017 |
| سطح | عملیاتی (Ops) |

---

## ۱. توپولوژی مرجع (لینوکس)

```
Internet → [CDN/WAF اختیاری] → nginx (TLS, gzip, rate-limit لایهٔ لبه)
        → php-fpm (N worker = هسته‌ها) → تَوان (public/index.php)
        → MySQL/PostgreSQL (یا SQLite برای بار سبک) + redis (cache/session/queue)
```

🔒 `php -S` (سرور داخلی) **فقط توسعه** است — تک‌رشته‌ای، بدون hardening.

## ۲. nginx (نمونهٔ کامل)

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;

    root /var/www/tavaan/public;
    index index.php;

    # TLS مدرن
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # 🔒 بدنهٔ درخواست — هم‌راستا با http.max_body_size (پیش‌فرض ۸MB)
    client_max_body_size 8m;
    client_body_buffer_size 128k;

    # 🔒 ضد Slowloris (نقد §3.3-5)
    client_header_timeout 10s;
    client_body_timeout   10s;
    send_timeout          10s;
    keepalive_timeout     15s;

    # rate-limit لایهٔ لبه ( complement محدودیت‌های اپ)
    limit_req_zone $binary_remote_addr zone=edge:10m rate=30r/s;
    limit_conn_zone $binary_remote_addr zone=conn:10m;

    # فقط index.php اجرا شود — هیچ .php دیگری
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        limit_req zone=edge burst=50 nodelay;
        limit_conn conn 20;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_read_timeout 30s;
        fastcgi_hide_header X-Powered-By;
    }
    # 🔒 مسیرهای حساس هرگز سرو نشوند
    location ~ /\.(env|git) { deny all; }
    location ~ ^/(storage|vendor|modules|engines|core)/ { deny all; }
}
```

## ۳. php-fpm (تنظیمworker)

```ini
; /etc/php/8.2/fpm/pool.d/tavaan.conf
pm = dynamic
pm.max_children = <RAM_کل − RAM_nginx/DB) ÷ ~40MB>   ; هر ورکر ~28–40MB (نقد §2)
pm.start_servers = <هسته‌ها>
pm.min_spare_servers = 2
pm.max_spare_servers = <هسته‌ها>
pm.max_requests = 500          ; 🔒 بازیافت worker → نشت حافظهٔ بالقوه صفر می‌شود
request_terminate_timeout = 30s
```

قاعدهٔ سرانگشتی `pm.max_children`: روی ماشین ۴ هسته/۸GB، معمولاً ۸–۱۶
(نه بیشتر — context-switch معکوس می‌شود؛ نقد §1 نشان داد scaling فراتر از
هسته بی‌فایده است).

## ۴. OPcache + Preload (کلید کارایی — ADR-0017)

```ini
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000     ; ریپو ~۲۰۰۰ فایل PHP
opcache.validate_timestamps=0           ; 🔒 در production خاموش (کد ثابت است)
opcache.preload=/var/www/tavaan/storage/preload.php
opcache.preload_user=www-data
```

پس از هر deploy: `tvn optimize` (کش config/classmap/route) و reload fpm.
`validate_timestamps=0` یعنی تغییر کد **نیاز به reload** دارد — در CI/CD
`systemctl reload php8.2-fpm` را بگذارید.

## ۵. پیکربندی اپ (`.env` تولید)

```
TVN_APP_ENV=production
TVN_APP_DEBUG=false              ; 🔒 هرگز true در تولید (نشت stack/secret)
TVN_CONFIG_CACHE=true            ; از storage/cache/config.php
TVN_AUTOLOAD_CACHE=true          ; classmap authoritative
TVN_LOG_LEVEL=warning            ; نه debug (حجم/نشت)
TVN_SESSION_SECURE=true          ; 🔒 کوکی فقط HTTPS
TVN_SESSION_SAME_SITE=Strict
TVN_DB_CONNECTION=mysql          ; SQLite فقط تک‌نود/سبک (نوشتن serialize می‌شود)
```

⚠️ `TVN_AUTOLOAD_CACHE=true` = classmap **authoritative**: کلاس جدید بدون
`tvn autoload:cache` مجدد پیدا نمی‌شود. در deploy hook: `tvn optimize`.

## ۶. لایه‌های دفاع (defense-in-depth — تکمیل نقد §3)

| لایه | ابزار | نکته |
|---|---|---|
| لبه | nginx limit_req/limit_conn + CDN/WAF | اولین خط؛ DDoS L7 را اینجا بگیرید نه اپ |
| نشست | session hardening (regenerate/fingerprint/HttpOnly/Secure/SameSite) | داخلی فریمورک — فقط `.env` را درست ببندید |
| CSRF | middleware سراسری (except صریح api/callback) | داخلی — 419 در تست تأیید شد |
| نرخ اپ | ThrottleMiddleware per-route + rate_limit | 60×200→429 اندازه‌گیری شد |
| ورودی | Validator + max_body/json + depth JSON | 20MB/JSON-5000-لایه رد شد |
| خروجی | escape-first view + Sanitizer + mask | XSS ذخیره‌شده escape شد |
| داده | prepared-only + allowlist + Crypt-at-rest | SQLi بی‌اثر؛ national_id رمزنگاری‌شده |
| کد | arch:check + security:scan + plugin امضاشده | در CI اجباری |
| حسابرسی | audit hash-chain + `tvn audit:verify` | tamper → verify شکست |

نکتهٔ صادقانه (نقد §3.3): اسکنر امنیتی **token-based** است نه taint-analysis؛
و queue worker تک‌فرایند است. این‌ها را با review انسانی + زیرساخت (redis
queue با supervisor) بپوشانید.

## ۷. استقرار چند‌نودی (scale افقی — ADR-0017 بند ۲)

- session/cache/queue روی **redis** (نه file/local) تا همهٔ نودها مشترک باشند.
- فایل‌های آپلود/مدیا روی object storage (S3-compatible — موتور filesystem).
- sticky session لازم نیست اگر session روی redis باشد.
- `tvn backup:run` زمان‌بندی‌شده روی یک نود؛ `audit:verify` دوره‌ای.
- cron: `tvn schedule:run` هر دقیقه (فقط یک نود، یا با قفل distributed).

## ۸. چک‌لیست deploy (کپی‌پذیر)

```
☐ .env تولید (APP_DEBUG=false, SESSION_SECURE=true, *_CACHE=true)
☐ composer/vendor وجود ندارد (صفر‌وابستگی) — فقط کد + storage
☐ tvn migrate --force          ☐ tvn rbac:sync
☐ tvn optimize (config+autoload+route)  ☐ storage/preload.php ساخته شد
☐ opcache.preload تنظیم + reload fpm     ☐ nginx TLS + timeouts + rate-limit
☐ storage/ نوشتنی توسط www-data و بیرون از docroot
☐ smoke: health_check هر کیت + tvn audit:verify سالم
☐ CI: tvn test + arch:check + security:scan (high=0) سبز
```
