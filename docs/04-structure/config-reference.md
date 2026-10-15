# DOC-085 — مرجع کامل پیکربندی (Config & ENV Reference) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-085 |
| وضعیت | Approved v1.0 |
| موتور | `config` (DOC-032) |
| نقش | مرجع یکپارچهٔ همهٔ ۴۸ فایل config و ۱۱۱ کلید ENV که در مستندات پراکنده‌اند |
| اصل | هر کلید پیش‌فرض منطقی دارد → پروژه بدون `.env` هم بالا می‌آید (DOC-032 §5) |

---

## ۱. اصول پیکربندی 🔒

| # | قاعده |
|---|---|
| 1 | فایل config فقط `return []` — بدون منطق و side effect |
| 2 | `env()` فقط در فایل‌های config مجاز است (نه کد کاربردی) |
| 3 | هر کلید پیش‌فرض دارد؛ `.env` فقط override است |
| 4 | کلید ENV با پیشوند `TVN_` و معادل کلید config |
| 5 | کلیدها lowercase + snake_case، حداکثر ۴ سطح عمق |
| 6 | در PROD، config کش و Frozen است (`set()` → خطا) |
| 7 | Secretها در `secret_keys` → mask در لاگ/`about` |

### ۱٫۱ قرارداد نام ENV
```
کلید config:  app.debug          →  ENV:  TVN_APP_DEBUG
کلید config:  db.connections.mysql.host  →  ENV:  TVN_DB_HOST
کلید config:  cache.default      →  ENV:  TVN_CACHE_DRIVER
```

---

## ۲. فایل‌های config (۴۸ فایل)

### ۲٫۱ هسته و اجرا
| فایل | کلید ریشه | موتور | سند |
|---|---|---|---|
| `config/app.php` | `app.*` | core | DOC-032 |
| `config/autoload.php` | `autoload.*` | core | DOC-082 |
| `config/providers.php` | (list) | core | DOC-027 |
| `config/modules.php` | `modules.*` | module | DOC-022 |
| `config/architecture.php` | `architecture.*` | core | DOC-021 |
| `config/container.php` | `container.*` | container | DOC-026 |
| `config/config.php` | `config.*` | config | DOC-032 |
| `config/pipeline.php` | `pipeline.*` | pipeline | DOC-034 |
| `config/event.php` | `event.*` | event | DOC-033 |
| `config/error.php` | `error.*` | error | DOC-035 |
| `config/compat.php` | `compat.*` | compat | DOC-083E |
| `config/console.php` | `console.*` | console | DOC-076 |

### ۲٫۲ وب
| فایل | کلید ریشه | موتور | سند |
|---|---|---|---|
| `config/http.php` | `http.*` | http | DOC-039 |
| `config/router.php` | `router.*` | router | DOC-040 |
| `config/session.php` | `session.*` | session | DOC-041 |
| `config/cors.php` | `cors.*` | cors | DOC-046 |
| `config/view.php` | `view.*` | view | DOC-043 |
| `config/api.php` | `api.*` | api | DOC-044 |
| `config/realtime.php` | `realtime.*` | realtime | DOC-045 |

### ۲٫۳ داده
| فایل | کلید ریشه | موتور | سند |
|---|---|---|---|
| `config/database.php` | `db.*` | database | DOC-047 |
| `config/orm.php` | `orm.*` | orm | DOC-050 |
| `config/search.php` | `search.*` | search | DOC-052 |
| `config/validation.php` | `validation.*` | validation | DOC-051 |

### ۲٫۴ امنیت و هویت
| فایل | کلید ریشه | موتور | سند |
|---|---|---|---|
| `config/security.php` | `security.*` | security | DOC-059 |
| `config/auth.php` | `auth.*` | auth | DOC-056 |
| `config/rbac.php` | `rbac.*` | rbac | DOC-057 |
| `config/hash.php` | `hash.*` | hash | DOC-055 |
| `config/audit.php` | `audit.*` | audit | DOC-058 |
| `config/tenancy.php` | `tenancy.*` | tenancy | DOC-082E |
| `config/plugins.php` | `plugins.*` | plugin | DOC-079 |

> 🔑 کلید `app.key` (APP_KEY) در `config/app.php` است اما رمزنگاری توسط موتور `crypt` (DOC-054).

### ۲٫۵ زیرساخت
| فایل | کلید ریشه | موتور | سند |
|---|---|---|---|
| `config/cache.php` | `cache.*` | cache | DOC-060 |
| `config/queue.php` | `queue.*` | queue | DOC-061 |
| `config/schedule.php` | `schedule.*` | schedule | DOC-062 |
| `config/log.php` | `log.*` | log | DOC-036 |
| `config/filesystem.php` | `filesystem.*` | filesystem | DOC-063 |
| `config/media.php` | `media.*` | media | DOC-074 |
| `config/mail.php` | `mail.*` | mail | DOC-065 |
| `config/notification.php` | `notification.*` | notification | DOC-066 |
| `config/http-client.php` | `http_client.*` | http-client | DOC-064 |
| `config/observability.php` | `observability.*` | observability | DOC-067 |
| `config/backup.php` | `backup.*` | backup | DOC-068 |
| `config/i18n.php` | `i18n.*` | i18n | DOC-069 |

### ۲٫۶ دامنه
| فایل | کلید ریشه | موتور | سند |
|---|---|---|---|
| `config/workflow.php` | `workflow.*` | workflow | DOC-071 |
| `config/report.php` | `report.*` | report | DOC-072 |
| `config/payment.php` | `payment.*` | payment | DOC-073 |
| `config/features.php` | `features.*` | feature-flag | DOC-075 |
| `config/approvals.php` | `approvals.*` | workflow | DOC-102 |
| `config/gateway.php` | `gateway.*` | api (microservice) | DOC-024 |

### ۲٫۷ ماژول‌ها
| فایل | کلید ریشه | منبع |
|---|---|---|
| `config/<module>.php` | `<module>.*` | هر ماژول (merge خودکار) |

---

## ۳. کلیدهای ENV (مرجع کامل ۱۱۱ کلید)

### ۳٫۱ اپلیکیشن (`app.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_APP_NAME` | string | `Tavaan` | نام اپلیکیشن |
| `TVN_APP_ENV` | string | `production` | `local\|development\|testing\|staging\|production` |
| `TVN_APP_DEBUG` | bool | `false` | 🔒 در production باید false باشد |
| `TVN_APP_URL` | string | `http://localhost` | URL پایه |
| `TVN_APP_KEY` | string | — | 🔒 `base64:…` (اجباری در prod) |
| `TVN_APP_LOCALE` | string | `fa` | زبان پیش‌فرض |
| `TVN_FALLBACK_LOCALE` | string | `en` | زبان جایگزین |
| `TVN_TIMEZONE` | string | `Asia/Tehran` | منطقهٔ زمانی |
| `TVN_SUPPORT_URL` | string | `null` | لینک پشتیبانی در صفحهٔ خطا |
| `TVN_DIGITS` | string | `persian` | `persian\|latin\|auto` |

### ۳٫۲ پایگاه داده (`database.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_DB_CONNECTION` | string | `mysql` | اتصال پیش‌فرض |
| `TVN_DB_HOST` | string | `127.0.0.1` | میزبان |
| `TVN_DB_PORT` | int | `3306` | پورت |
| `TVN_DB_DATABASE` | string | `tavaan` | نام دیتابیس |
| `TVN_DB_USERNAME` | string | `root` | کاربر |
| `TVN_DB_PASSWORD` | secret | `''` | 🔒 رمز |
| `TVN_DB_SOCKET` | string | `''` | unix socket |
| `TVN_DB_PREFIX` | string | `''` | پیشوند جدول |
| `TVN_DB_READ_HOST` | list | `[]` | replicaها |
| `TVN_DB_QUERY_LOG` | bool | `false` | لاگ کوئری |
| `TVN_DB_N1_DETECT` | bool | `false` | تشخیص N+1 |

### ۳٫۳ HTTP (`http.php`, `router.php`, `session.php`, `cors.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_HTTP_DRIVER` | string | `fpm` | `fpm\|builtin\|swoole\|frankenphp` |
| `TVN_HTTP_HOST` | string | `127.0.0.1` | میزبان سرور |
| `TVN_HTTP_PORT` | int | `8080` | پورت سرور |
| `TVN_HTTP_WORKERS` | int | `0` | تعداد worker (۰=auto) |
| `TVN_TRUSTED_PROXIES` | list | `[]` | 🔒 پروکسی‌های مورد اعتماد |
| `TVN_ROUTE_CACHE` | bool | `false` | کش روت |
| `TVN_FORCE_HTTPS` | bool | `false` | اجبار HTTPS |
| `TVN_ASSET_URL` | string | `null` | پایهٔ asset |
| `TVN_SESSION_DRIVER` | string | `file` | `file\|redis\|database\|cookie\|array` |
| `TVN_SESSION_LIFETIME` | int | `120` | دقیقه |
| `TVN_SESSION_COOKIE` | string | `tavaan_session` | نام کوکی |
| `TVN_SESSION_DOMAIN` | string | `null` | دامنهٔ کوکی |
| `TVN_SESSION_SECURE` | bool | `false` | 🔒 در prod: true |
| `TVN_SESSION_SAME_SITE` | string | `lax` | `lax\|strict\|none` |
| `TVN_SESSION_IDLE` | int | `1800` | timeout بیکاری (ثانیه) |
| `TVN_SESSION_ABSOLUTE` | int | `43200` | timeout مطلق (ثانیه) |
| `TVN_CORS_ORIGINS` | list | `[]` | 🔒 originهای مجاز |

### ۳٫۴ کش و صف (`cache.php`, `queue.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_CACHE_DRIVER` | string | `file` | `array\|file\|redis\|memcached\|apcu\|database` |
| `TVN_CACHE_PREFIX` | string | `tvn:` | پیشوند کلید |
| `TVN_CACHE_LOCK_DRIVER` | string | `file` | درایور Lock |
| `TVN_REDIS_CACHE_CONNECTION` | string | `cache` | اتصال Redis |
| `TVN_QUEUE_CONNECTION` | string | `database` | `sync\|database\|redis\|file\|null` |
| `TVN_QUEUE_MAX_TRIES` | int | `3` | حداکثر تلاش job |
| `TVN_REDIS_HOST` / `TVN_REDIS_PORT` / `TVN_REDIS_PASSWORD` | — | — | اتصال Redis |

### ۳٫۵ لاگ (`log.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_LOG_CHANNEL` | string | `daily` | کانال پیش‌فرض |
| `TVN_LOG_LEVEL` | string | `debug` | حداقل سطح |
| `TVN_LOG_FORMAT` | string | `line` | `line\|json` |
| `TVN_LOG_QUERY` | bool | `false` | لاگ کوئری‌ها |

### ۳٫۶ ایمیل (`mail.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_MAIL_MAILER` | string | `smtp` | `smtp\|sendmail\|log\|array\|ses` |
| `TVN_MAIL_HOST` | string | — | میزبان SMTP |
| `TVN_MAIL_PORT` | int | `587` | پورت |
| `TVN_MAIL_ENCRYPTION` | string | `tls` | `tls\|ssl\|null` |
| `TVN_MAIL_USERNAME` | string | — | کاربر |
| `TVN_MAIL_PASSWORD` | secret | — | 🔒 رمز |
| `TVN_MAIL_FROM_ADDRESS` | string | — | فرستنده |
| `TVN_MAIL_FROM_NAME` | string | — | نام فرستنده |

### ۳٫۷ پیامک و آگاه‌سازی (`notification.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_SMS_DRIVER` | string | `kavenegar` | `kavenegar\|melipayamak\|…` |
| `TVN_NOTIFICATION_QUEUE` | bool | `true` | ارسال از طریق صف |
| `KAVENEGAR_KEY` / `KAVENEGAR_SENDER` | secret | — | 🔒 اعتبارنامه |
| `MP_USER` / `MP_PASS` | secret | — | 🔒 اعتبارنامه |

### ۳٫۸ پرداخت (`payment.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_PAYMENT_GATEWAY` | string | `zarinpal` | درگاه پیش‌فرض |
| `TVN_PAYMENT_CURRENCY` | string | `IRT` | `IRR\|IRT\|USD\|EUR` |
| `ZARINPAL_MERCHANT` | secret | — | 🔒 merchant_id |
| `ZARINPAL_SANDBOX` | bool | `false` | حالت آزمایشی |
| `BPM_TERMINAL` / `BPM_USERNAME` / `BPM_PASSWORD` | secret | — | 🔒 به‌پرداخت ملت |

### ۳٫۹ امنیت و رمزنگاری (`security.php`, `audit.php`, `backup.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_AUDIT_ENABLED` | bool | `true` | فعال‌سازی ممیزی |
| `TVN_AUDIT_KEY` | secret | — | 🔒 کلید زنجیرهٔ هش (جدا از APP_KEY) |
| `TVN_AUDIT_QUEUE` | bool | `false` | ممیزی async |
| `TVN_BACKUP_KEY` | secret | — | 🔒 کلید رمزنگاری backup |
| `TVN_PLUGIN_SIGNATURE` | bool | `true` | اجبار امضای پلاگین |
| `TVN_PLUGINS` | bool | `true` | فعال‌سازی پلاگین‌ها |

### ۳٫۱۰ رصد و Realtime (`observability.php`, `realtime.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_TRACING` | bool | `false` | فعال‌سازی tracing |
| `TVN_TRACE_EXPORTER` | string | `log` | `log\|otlp\|jaeger\|array` |
| `TVN_TRACE_SAMPLE` | float | `1.0` | نرخ نمونه‌برداری |
| `OTEL_EXPORTER_OTLP_ENDPOINT` | string | — | endpoint OTLP |
| `TVN_REALTIME_DRIVER` | string | `sse` | `sse\|websocket\|swoole` |
| `TVN_REALTIME_BROKER` | string | `database` | `redis\|database\|array` |
| `TVN_DEBUG_BAR` | bool | `false` | Debug Bar (فقط dev) |

### ۳٫۱۱ چنداجاره‌ای (`tenancy.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_TENANCY` | bool | `false` | فعال‌سازی |
| `TVN_TENANCY_STRATEGY` | string | `shared` | `shared\|database\|schema` |

### ۳٫۱۲ بهینه‌سازی و سازگاری (`compat.php`, `optimizer`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_AUTOLOAD_CACHE` | bool | `false` | کش ClassMap |
| `TVN_CONFIG_CACHE` | bool | `false` | کش config |
| `TVN_VIEW_CACHE` | bool | `false` | کش ویو |
| `TVN_CONTAINER_REFLECTION_CACHE` | bool | `false` | کش Reflection |
| `TVN_CONTAINER_COMPILED` | bool | `false` | کانتینر کامپایل‌شده |
| `TVN_CONTAINER_STATS` | bool | `false` | آمار کانتینر (dev) |
| `TVN_MATH_DRIVER` | string | `auto` | `auto\|bcmath\|gmp\|decimal` |
| `TVN_IMAGE_DRIVER` | string | `auto` | `auto\|imagick\|gd\|none` |
| `TVN_ZIP_DRIVER` | string | `auto` | `auto\|zip\|pure` |
| `TVN_HTTP_DRIVER` (client) | string | `auto` | `auto\|curl\|stream` |
| `TVN_UNICODE_DRIVER` | string | `auto` | `auto\|intl\|mbstring\|native` |
| `TVN_MIME_DRIVER` | string | `auto` | `auto\|fileinfo\|magic` |
| `TVN_COMPAT_FORCE_FALLBACK` | bool | `false` | اجبار Fallback (تست) |
| `TVN_COMPAT_WARN` | bool | `true` | هشدار در Fallback |

### ۳٫۱۳ جست‌وجو و زمان‌بندی (`search.php`, `schedule.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_SEARCH_DRIVER` | string | `mysql` | `mysql\|pgsql\|sqlite\|native\|…` |
| `TVN_SCHEDULE_TZ` | string | `Asia/Tehran` | منطقهٔ زمانی scheduler |
| `TVN_SCHEDULE_CACHE` | string | `null` | store برای mutex |

### ۳٫۱۴ گزارش (`report.php`)
| ENV | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `TVN_PDF_DRIVER` | string | `null` | درایور PDF (اختیاری) |
| `TVN_FILESYSTEM_DISK` | string | `local` | دیسک پیش‌فرض |

---

## ۴. `.env.example` کامل (مرجع)

```bash
# ── اپلیکیشن ───────────────────────────────────────────────
TVN_APP_NAME=Tavaan
TVN_APP_ENV=local
TVN_APP_DEBUG=true
TVN_APP_URL=http://localhost:8000
TVN_APP_KEY=
TVN_APP_LOCALE=fa
TVN_FALLBACK_LOCALE=en
TVN_TIMEZONE=Asia/Tehran
TVN_DIGITS=persian

# ── پایگاه داده ────────────────────────────────────────────
TVN_DB_CONNECTION=mysql
TVN_DB_HOST=127.0.0.1
TVN_DB_PORT=3306
TVN_DB_DATABASE=tavaan
TVN_DB_USERNAME=root
TVN_DB_PASSWORD=

# ── کش / صف / نشست ─────────────────────────────────────────
TVN_CACHE_DRIVER=file
TVN_QUEUE_CONNECTION=database
TVN_SESSION_DRIVER=file
TVN_SESSION_LIFETIME=120

# ── HTTP ───────────────────────────────────────────────────
TVN_HTTP_DRIVER=fpm
TVN_TRUSTED_PROXIES=
TVN_CORS_ORIGINS=

# ── ایمیل ──────────────────────────────────────────────────
TVN_MAIL_MAILER=log
TVN_MAIL_HOST=
TVN_MAIL_PORT=587
TVN_MAIL_ENCRYPTION=tls
TVN_MAIL_USERNAME=
TVN_MAIL_PASSWORD=
TVN_MAIL_FROM_ADDRESS=
TVN_MAIL_FROM_NAME=

# ── رمزنگاری (کلیدهای جدا) ─────────────────────────────────
TVN_AUDIT_KEY=
TVN_BACKUP_KEY=

# ── بهینه‌سازی ─────────────────────────────────────────────
TVN_AUTOLOAD_CACHE=false
TVN_CONFIG_CACHE=false
TVN_ROUTE_CACHE=false
TVN_VIEW_CACHE=false

# ── رصد (اختیاری) ──────────────────────────────────────────
TVN_TRACING=false
TVN_DEBUG_BAR=false
```

---

## ۵. اولویت پیکربندی (Precedence) 🔒

```
بالاترین  ←  پایین‌ترین:
1. Config Server (etcd/Consul)      [فقط در حالت سرویس — DOC-024 §10]
2. Runtime set() در Provider         [فقط dev]
3. ENV / $_SERVER / $_ENV
4. .env.<environment>
5. .env
6. ماژول config (merge)
7. موتور config (merge)
8. config/*.php پیش‌فرض
9. پیش‌فرض درون کد (Config::get default)
```

---

## ۶. اعتبارسنجی Schema

هر فایل config می‌تواند schema داشته باشد (`config/schema/<name>.schema.php`):
```php
// config/schema/app.schema.php
return [
    'name'     => ['type'=>'string','max'=>100,'required'=>true],
    'env'      => ['type'=>'string','in'=>['local','development','testing','staging','production']],
    'debug'    => ['type'=>'bool'],
    'timezone' => ['type'=>'string','rule'=>'timezone'],
    'locale'   => ['type'=>'string','pattern'=>'/^[a-z]{2}(_[A-Z]{2})?$/'],
    'key'      => ['type'=>'string','pattern'=>'/^base64:[A-Za-z0-9+\/=]{44}$/','secret'=>true],
];
```
🔒 در dev: همهٔ قواعد بررسی می‌شوند. در prod: فقط `required` و `in` (برای سرعت).
→ جزئیات در DOC-032 §5.5.

---

## ۷. Secretها 🔒

کلیدهایی که با الگوهای زیر مطابقت کنند، **خودکار mask** می‌شوند:
```
config/config.php → secret_keys:
  '*.password', '*.secret', '*.key', '*.token',
  'db.*.password', 'mail.*.password', '*.api_key'
```

| قاعده |
|---|
| 🔒 هرگز Secret هاردکد نکن — فقط ENV |
| 🔒 `TVN_APP_KEY`، `TVN_AUDIT_KEY`، `TVN_BACKUP_KEY` جدا باشند |
| 🔒 در `tvn about --json` همهٔ Secretها `********` |
| 🔒 `.env` هرگز در Git یا `public/` نباشد |
| 🔒 CI شامل Secret Scanner (DOC-016 §4) |

---

## ۸. ابزارهای مرتبط

| دستور | کاربرد |
|---|---|
| `tvn env:check` | کلیدهای گمشده نسبت به `.env.example` |
| `tvn config:cache` / `config:clear` | کش/پاک‌کردن config |
| `tvn about` | نمایش config (masked) |
| `tvn doctor` | تشخیص config + fallback |

---

## ۹. معیار پذیرش 🧪
- AC-CFG-REF-01: همهٔ ۴۸ فایل config پیش‌فرض کامل دارند (پروژه بدون `.env` بالا می‌آید).
- AC-CFG-REF-02: `tvn env:check` کلیدهای گمشده را گزارش می‌دهد.
- AC-CFG-REF-03: همهٔ Secretها در `about`/لاگ mask می‌شوند.
- AC-CFG-REF-04: اولویت §5 درست رعایت می‌شود (تست).
- AC-CFG-REF-05: Schema validation در dev همهٔ کلیدها را بررسی می‌کند.
- AC-CFG-REF-06: هیچ `env()` خارج از فایل‌های config نیست (بررسی AST).
