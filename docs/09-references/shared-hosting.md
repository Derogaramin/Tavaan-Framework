# DOC-134 — راهنمای هاست اشتراکی و پیکربندی سه‌سطحی php.ini 🏠

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-134 |
| نقش | استقرار تَوان روی **هاست اشتراکی** + پیکربندی سه‌سطحی `php.ini` + گزارش اندازه‌گیری منابع |
| مرتبط | DOC-133 (استقرار/Hardening)، DOC-112 (کارایی)، ADR-0017 (process-per-request)، DOC-030 (پیکربندی)، DOC-130 (بنچمارک) |
| سطح | عملیاتی (Ops) — ویژهٔ میزبانی اشتراکی |
| وضعیت | ✅ اندازه‌گیری‌شده روی PHP 8.2.35 / Debian 12 / ۲ vCPU / ~۱GB RAM |
| ابزار | `tools/scripts/shared-host-bench.php`، `worker-rss.php`، `memory-ceiling.php`، `bench-server.php`، `shared-host-measure.php`، `boot-profile.php` |
| پروفایل‌ها | `examples/hosting/php-{minimum,ideal,heavy,restricted}.ini` |

---

## ۱. هاست اشتراکی چیست؟ (پاسخ به پرسش مفهومی)

**هاست اشتراکی (Shared Hosting)** مدلی از میزبانی وب است که در آن صدها تا هزاران
سایت روی **یک سرور فیزیکی** قرار می‌گیرند و منابع آن (CPU، RAM، دیسک، پهنای
باند) را **به‌اشتراک** می‌گذارند. هر کاربر یک فضای ایزوله (معمولاً cPanel یا
DirectAdmin) دارد اما **دسترسی root ندارد** و نمی‌تواند سرویس‌های دلخواه نصب کند.

### ۱٫۱ ویژگی‌های کلیدی

| ویژگی | توضیح | پیامد برای تَوان |
|---|---|---|
| **منابع سهمیه‌بندی‌شده** | سقف CPU% (مثلاً ۱۰۰–۲۰۰٪ یک هسته)، RAM (۵۱۲MB–۲GB)، Entry Processes (تعداد فرآیند هم‌زمان، معمولاً ۲۰–۴۰)، I/O و IOPS | فریمورک باید **سبک و سریع** باشد؛ هر ms مهم است |
| **وب‌سرور ثابت** | Apache یا **LiteSpeed** با `mod_lsapi`/PHP-FPM؛ کاربر فقط PHP را انتخاب می‌کند | بدون کنترل روی nginx؛ hardening لایهٔ وب محدود است |
| **PHP چند نسخه‌ای** | انتخاب نسخه از پنل (MultiPHP) + ویرایش `php.ini`/`.user.ini` | ✅ تَوان به PHP ≥ 8.2 نیاز دارد (بیشتر هاست‌ها دارند) |
| **پایگاه‌داده MySQL/MariaDB** | اشتراکی، گاهی با `max_connections` و timeout محدود | ✅ ORM تَوان MySQL-first است؛ SQLite برای بار سبک |
| **بدون فرآیند مقیم** | اجازهٔ daemon/websocket/queue worker دائم **نیست** | ✅ ADR-0017: تَوان **process-per-request** است — ذاتاً سازگار |
| **cron محدود** | معمولاً حداقل هر ۵/۱۵ دقیقه یک‌بار | ✅ `schedule:run` + `queue:work --stop-when-empty` با cron |
| **`disable_functions` سخت‌گیرانه** | `proc_open`, `exec`, `shell_exec`, `putenv`, `pcntl_*`, `symlink` اغلب بسته‌اند | ✅ مسیر وب تَوان به هیچ‌کدام وابستگی سخت ندارد (§6 — تست‌شده) |
| **دسترسی فایل** | فقط زیر `public_html`/`home`؛ مجوز نوشتن روی `storage/` | ✅ تَوان cache/session/log را در `storage/` می‌نویسد |
| **بدون Composer معمولاً** | یا SSH محدود یا فقط FTP/File Manager | ✅ تَوان **صفر وابستگی Composer** دارد — کد را آپلود کنید و تمام |
| **OPcache اشتراکی** | اغلب فعال اما با `memory_consumption` و `max_accelerated_files` محدود؛ `validate_timestamps` گاهی قفل | ⚠️ کلید کارایی — §۴ و §۵ |

### ۱٫۲ مشخصات یک پلن اشتراکی نمونه

```
CPU:        ۱–۲ هستهٔ اشتراکی (محدود به ~۱۰۰–۲۰۰٪ یک هسته)
RAM:        ۵۱۲MB – ۲GB (سهمیهٔ کل، نه هر فرآیند)
Entry Proc: ۲۰–۴۰ فرآیند PHP هم‌زمان
دیسک:       ۵–۵۰GB (SSD/NVMe)
DB:         MySQL 5.7+/MariaDB 10.3+، ~۵۰ اتصال
PHP:        8.1 / 8.2 / 8.3 (انتخابی)
وب‌سرور:    LiteSpeed + LSCache یا Apache + mod_lsapi
پنل:        cPanel / DirectAdmin
دسترسی:     FTP/SFTP، File Manager، SSH محدود، cron، phpMyAdmin
```

### ۱٫۳ چرا مدل تَوان برای اشتراکی «ایده‌آل» است

تَوان بر پایهٔ **process-per-request** طراحی شده (ADR-0017): هر درخواست یک
فرآیند مستقل است که بوت می‌شود، پاسخ می‌دهد و آزاد می‌شود — **بدون حافظهٔ
مقیم بین درخواست‌ها**. این دقیقاً همان مدلی است که LiteSpeed/PHP-FPM در هاست
اشتراکی اجرا می‌کند. برخلاف فریمورک‌هایی که به resident runtime (Swoole/RoadRunner)
یا daemon نیاز دارند، تَوان **روی هر هاست اشتراکی بدون تنظیمات خاص کار می‌کند**.

---

## ۲. محیط شبیه‌سازی (روش اندازه‌گیری)

برای پاسخ به «این فریمورک چقدر منبع می‌خواهد؟»، یک هاست اشتراکی را با دقت
شبیه‌سازی کردیم:

| جزء | شبیه‌سازی | معادل واقعی |
|---|---|---|
| وب‌سرور + PHP | `php -S` با worker پایدار (`PHP_CLI_SERVER_WORKERS`) | LiteSpeed/PHP-FPM با worker مقیم |
| OPcache | `opcache.enable_cli=1` (گرم بین درخواست‌ها در worker) | OPcache اشتراکی fpm |
| پیکربندی | `php -c examples/hosting/php-<tier>.ini` | MultiPHP INI Editor |
| پایگاه‌داده | SQLite (بدون network hop) | MySQL اشتراکی (کمی کندتر) |
| محدودیت تابع‌ها | پروفایل `restricted` با `disable_functions` | `disable_functions` میزبان |
| سخت‌افزار | ۲ vCPU اشتراکی Xeon @2.5GHz، ~۱GB RAM، Debian 12 | یک پلن اشتراکی کوچک |

> ⚠️ **اعداد «کف بدبینانه» هستند.** `php -S` از LiteSpeed واقعی کندتر است،
> SQLite سریع‌تر از MySQL شبکه‌ای است (اثر خنثی‌کننده)، و تولیدکنندهٔ بار هم
> CPU مصرف می‌کند. روی میزبانی واقعی با LSCache، اعداد HTTP معمولاً **بهتر**
> خواهند بود. هدف، اثبات «پاسخگویی مناسب» و مقایسهٔ نسبی سطوح است.

### ۲٫۱ سه سناریوی اندازه‌گیری

| سناریو | مسیر | پوشش |
|---|---|---|
| `time` | `GET /time` | JSON از closure — سبک‌ترین مسیر کامل (router→kernel→response) |
| `view` | `GET /` | صفحهٔ welcome — موتور View + کامپایل قالب |
| `db` | `GET /blog` | ماژول blog → SQLite + ORM + View + Session + Route |

---

## ۳. پیکربندی سه‌سطحی php.ini (هم‌تراز صورت‌مسئله)

مانند نمونه‌های وردپرس/لاراول/سیمفونی که داده شد، تَوان هم در **سه سطح**
پیکربندی و تست شد. فایل‌ها در `examples/hosting/` هستند.

### ۳٫۱ سطح «حداقل» (`php-minimum.ini`) — ارزان‌ترین پلن

```ini
memory_limit = 128M              ; سقف اندازه‌گیری‌شدهٔ HTTP معمول تَوان: ~۶MB (§۵٫۱) — ۲۰× حاشیه
max_execution_time = 60          ; سنگین‌ترین صفحهٔ وب (BPMS/ERP): <۲s
max_input_time = 60
max_input_vars = 3000            ; فرم‌ساز پویا BPMS + فرم چندبخشی
upload_max_filesize = 8M         ; هم‌راستا با http.max_body_size=8M
post_max_size = 10M
opcache.enable = 1
opcache.memory_consumption = 64  ; footprint واقعی تَوان: ~۱۳MB (§۵٫۳) — ۵× حاشیه
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000   ; کل فایل‌های PHP ریپو ~۲۴۰۰
opcache.validate_timestamps = 1  ; در حداقل: دیپلوی بدون reset (امن‌تر برای مبتدی)
opcache.revalidate_freq = 2
opcache.save_comments = 1        ; ⚠️ حیاتی — DI تَوان docblock-محور است
realpath_cache_size = 1024K
realpath_cache_ttl = 120
display_errors = Off
log_errors = On
expose_php = Off
date.timezone = Asia/Tehran
```

### ۳٫۲ سطح «ایده‌آل» (`php-ideal.ini`) — تنظیم پیشنهادی رسمی

```ini
memory_limit = 256M              ; گزارش/ایمپورت تا ~۱۰۰k رکورد (§۵٫۵)
max_execution_time = 120         ; مهاجرت/ایمپورت CSV از وب
max_input_time = 60
max_input_vars = 5000
upload_max_filesize = 32M        ; هم‌راستا با media.upload_max کیت‌ها
post_max_size = 35M
opcache.enable = 1
opcache.memory_consumption = 128 ; footprint ~۲۲MB + رشد ماژول/کیت/پلاگین کاربر
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000   ; ۸ کیت + ماژول‌ها + پلاگین‌های کاربر
opcache.validate_timestamps = 0  ; 🔒 production: صفر stat() → سریع‌تر (پس از دیپلوی reset)
opcache.revalidate_freq = 0
opcache.save_comments = 1        ; ⚠️ حیاتی
realpath_cache_size = 4096K
realpath_cache_ttl = 600
; opcache.preload = /home/USER/tavaan/storage/preload.php   ; در صورت اجازهٔ میزبان
session.cookie_httponly = 1
session.cookie_secure = 1        ; اگر HTTPS قطعی است
session.cookie_samesite = Lax
display_errors = Off
log_errors = On
expose_php = Off
date.timezone = Asia/Tehran
```

### ۳٫۳ سطح «سنگین» (`php-heavy.ini`) — سایت پربازدید / BI / ایمپورت بزرگ

```ini
memory_limit = 512M              ; گزارش ۱۵۰k+ ردیف، ایمپورت بزرگ، پردازش متنی
max_execution_time = 300         ; backup/گزارش ERP/ایمپورت
max_input_time = 300
max_input_vars = 10000           ; فرم‌ساز BPMS بزرگ + جداول editable ERP
upload_max_filesize = 64M
post_max_size = 72M
opcache.enable = 1
opcache.enable_cli = 0           ; 🔒 در اشتراکی ۰ بماند (cron هر بار SHM را پر نکند)
opcache.memory_consumption = 256 ; footprint ~۳۹MB + همهٔ کیت/پلاگین
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 40000
opcache.validate_timestamps = 0  ; 🔒 دیپلوی = آپلود + tvn optimize + reset opcache
opcache.revalidate_freq = 0
opcache.save_comments = 1        ; ⚠️ حیاتی — برخلاف سمفونی، تَوان docblock-محور است
realpath_cache_size = 8192K
realpath_cache_ttl = 3600
session.gc_probability = 0       ; 🔒 GC را cron تَوان (schedule:run) مدیریت می‌کند
display_errors = Off
log_errors = On
expose_php = Off
date.timezone = Asia/Tehran
```

### ۳٫۴ سطح «محدود» (`php-restricted.ini`) — بدترین حالت واقعی

بسیاری از هاست‌های اشتراکی (به‌ویژه ایران) تابع‌های فرآیندی/محیطی را با
`disable_functions` می‌بندند. این پروفایل همان `ideal` است به‌علاوهٔ:

```ini
disable_functions = pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait,pcntl_signal,\
proc_open,popen,shell_exec,exec,passthru,system,putenv,symlink
; open_basedir = /home/USER/tavaan:/tmp
```

### ۳٫۵ چرا `save_comments=1` برای تَوان حیاتی است؟

برخلاف سمفونی (که `save_comments=0` را در production توصیه می‌کند چون از
Attribute استفاده می‌کند)، **DI و مستندسازی تَوان به docblock تکیه دارد**
(ReflectionCache در Container). اگر `save_comments=0` شود، auto-wiring و
`@api`/`@see` می‌شکنند. **در همهٔ سطوح روی ۱ بماند.**

---

## ۴. نتایج — بوت و حالت پایدار (Warm)

### ۴٫۱ بوت سرد در برابر گرم (مهم‌ترین یافته)

هر فرآیند CLI با OPcache **سرد** شروع می‌کند (کد را از نو کامپایل می‌کند)؛
اما در هاست واقعی worker **پایدار** است و OPcache بین درخواست‌ها **گرم**
می‌ماند. تفاوت چشمگیر است:

| حالت | app_boot | کل اولین درخواست | حافظهٔ اوج | p50 پایدار |
|---|---|---|---|---|
| **سرد + OPcache** (اولین درخواست پس از دیپلوی) | ۷۸–۱۰۵ms | ۱۰۰–۱۲۵ms | ۴–۶MB | — |
| **گرم + OPcache** (حالت پایدار) | — | — | **۴MB** | **۲٫۶–۳٫۵ms** |
| **بدون OPcache** (هر درخواست از نو کامپایل) | ۳۹–۴۳ms | ۴۹–۶۳ms | ۸–۱۰MB | ۸٫۲–۱۶ms |

> 🔑 **نتیجهٔ کلیدی:** OPcache یک‌بار ~۵۰ms در بوت سرد هزینه می‌کند، اما سپس
> **هر درخواست ~۳× سریع‌تر** (۲٫۶ms در برابر ۸٫۲ms) و با **نصف حافظه** (۴MB
> در برابر ۸MB) اجرا می‌شود. روی هاست اشتراکی که هر worker هزاران درخواست را
> سرو می‌کند، OPcache **بدون استثنا برنده است**. تنها حالتی که «بدون OPcache»
> به‌صرفه است، یک اسکریپت تک‌اجرایی (cron یک‌بار) است.

### ۴٫۲ زمان پایدار به تفکیک پروفایل و سناریو (۲۰۰ درخواست، OPcache گرم)

| پروفایل | سناریو | p50 | p95 | بیشینه | اوج حافظه | OPcache مصرفی | فایل/کلاس |
|---|---|---|---|---|---|---|---|
| **minimum** | /time | ۲٫۵۸ms | ۳٫۶۲ms | ۶٫۶ms | ۴MB | ۱۲٫۹MB | ۳۳۵ فایل |
| | / | ۲٫۹۴ms | ۳٫۶۱ms | ۶٫۲ms | ۴MB | ۱۳٫۰MB | ۳۳۹ فایل |
| | /blog | ۷٫۶۴ms | ۱۰٫۵۳ms | ۶۳ms | ۴MB | ۱۳٫۵MB | ۳۶۸ فایل |
| **ideal** | /time | ۳٫۵۳ms | ۶٫۱۹ms | ۹٫۸ms | ۴MB | ۲۱٫۷MB | ۳۳۵ فایل |
| | / | ۳٫۵۹ms | ۶٫۵۲ms | ۱۳٫۵ms | ۴MB | ۲۱٫۷MB | ۳۳۹ فایل |
| | /blog | ۸٫۶۵ms | ۱۱٫۴۹ms | ۲۰٫۵ms | ۴MB | ۲۲٫۲MB | ۳۶۸ فایل |
| **heavy** | /time | ۳٫۳۷ms | ۴٫۴۱ms | ۸٫۳ms | ۴MB | ۳۹٫۲MB | ۳۳۵ فایل |
| | / | ۳٫۵۰ms | ۵٫۱۰ms | ۱۱٫۸ms | ۴MB | ۳۹٫۲MB | ۳۳۹ فایل |
| | /blog | ۶٫۰۳ms | ۹٫۷۱ms | ۱۹٫۲ms | ۴MB | ۳۹٫۷MB | ۳۶۸ فایل |
| **restricted** | /time | ۲٫۹۲ms | ۵٫۴۹ms | ۱۲٫۷ms | ۴MB | ۱۳٫۷MB | ۳۳۵ فایل |
| | / | ۳٫۵۵ms | ۵٫۹۹ms | ۱۵٫۵ms | ۴MB | ۱۳٫۷MB | ۳۳۹ فایل |
| | /blog | ۸٫۵۱ms | ۱۱٫۸۳ms | ۲۰٫۱ms | ۴MB | ۱۴٫۲MB | ۳۶۸ فایل |
| **noopt** | /time | ۸٫۱۶ms | ۱۰٫۰۶ms | ۱۹٫۵ms | ۸MB | ۰ (خاموش) | ۳۳۵ فایل |
| | / | ۸٫۴۵ms | ۱۱٫۶۰ms | ۲۰٫۴ms | ۸MB | ۰ | ۳۳۹ فایل |
| | /blog | ۱۶٫۰۳ms | ۱۹٫۶۴ms | ۲۸٫۸ms | ۱۰MB | ۰ | ۳۶۸ فایل |

**تحلیل:**
- همهٔ پروفایل‌های دارای OPcache در محدودهٔ **۲٫۵–۹ms** پایدارند — عالی برای اشتراکی.
- `restricted` (بدون `proc_open`/`putenv`) **هیچ افتی ندارد** → سازگاری کامل با میزبان‌های سخت‌گیر.
- `noopt` ~۳× کندتر و ۲× پرمصرف‌تر است → **هرگز OPcache را خاموش نکنید.**
- `heavy` در `/blog` کمی سریع‌تر از `ideal` است (p50 ۶ms در برابر ۸٫۶ms) به‌خیر `realpath_cache_ttl=3600` و buffer بزرگ‌تر.

### ۴٫۳ تجزیهٔ بوت (کجا صرف می‌شود؟)

`app_boot≈۸۰–۱۰۵ms` (سرد) به این bootstrapperها تقسیم می‌شود
(`tools/scripts/boot-profile.php`):

| Bootstrapper | زمان (سرد) | یادداشت |
|---|---|---|
| LoadEnvironmentVariables | ۱٫۲ms | خواندن `.env` |
| LoadConfiguration | ۷٫۹ms | با `config:cache` → ~۲ms |
| HandleExceptions | ۳٫۰ms | ثبت error handler |
| RegisterFacades | ۰٫۱ms | — |
| **RegisterProviders** | **۲۸٫۵ms** | ثبت ~۵۰ provider (بزرگ‌ترین سهم) |
| **DiscoverModules** | **۱۰٫۶ms** | کشف ماژول/کیت — با کش کمتر می‌شود |
| **BootProviders** | **۲۴٫۰ms** | اجرای `boot()` همهٔ providerها |
| SetLocaleAndTimezone | ۰٫۳ms | — |

> در حالت **گرم** (OPcache) این ~۸۰ms به ~۲–۳ms می‌رسد (کلاس‌ها از SHM).
> `tvn optimize` (کش config+autoload) سهم `LoadConfiguration` و autoload را
> بیشتر کاهش می‌دهد.

### ۴٫۴ OPcache Preload (حذف بوت سرد)

`examples/hosting/preload.php` کل classmap (~۱۵۸۰ فایل هسته/موتور/ماژول فعال)
را پیش‌کامپایل می‌کند تا بوت سرد ~۸۰–۱۰۵ms (§4.3) به نزدیک صفر برسد.

| بررسی | نتیجه |
|---|---|
| اجرای مستقیم اسکریپت (`opcache.enable_cli=1`) | ✅ `preloaded=1580 failed=0` |
| نیاز به `opcache.preload_user` | ⚠️ **الزامی** — PHP بدون آن خطای Fatal می‌دهد: `"opcache.preload_user" has not been defined` |
| اجرا به‌عنوان root | ❌ PHP به‌دلایل امنیتی preload را به‌عنوان root اجرا نمی‌کند |

> 📌 **یادداشت اندازه‌گیری:** اثر preload بر بوت سرد در این sandbox قابل اندازه‌گیری
> نبود چون محیط به‌عنوان **root** اجرا می‌شود و کاربر غیر-root (`nobody`) به مسیر
> workspace دسترسی نداشت. رفتار «حذف کامپایل در اولین درخواست» مستندِ استاندارد
> OPcache است؛ برای فعال‌سازی واقعی، `opcache.preload` + `opcache.preload_user`
> (کاربر وب‌سرور) را در php.ini اصلی ست کنید و سرویس را reload نمایید. در هاست
> اشتراکی این اغلب **در دسترس نیست** (نیاز به php.ini سطح سیستم دارد) — اگر
> میزبان اجازه نداد، همان `validate_timestamps=0` + worker پایدار، بوت سرد را
> فقط به **اولین درخواست پس از reload** محدود می‌کند و بقیه گرم‌اند.

### ۴٫۵ footprint در OPcache و RSS ورکر

| پروفایل | OPcache SHM (مصرفی) | `memory_consumption` | RSS ورکر گرم |
|---|---|---|---|
| minimum | ۱۲٫۹–۱۳٫۵MB | ۶۴MB | **۳۷–۳۹MB** |
| ideal | ۲۱٫۷–۲۲٫۲MB | ۱۲۸MB | **۳۹–۴۲MB** |
| heavy | ۳۹٫۲–۳۹٫۷MB | ۲۵۶MB | **۴۴–۴۶MB** |
| restricted | ۱۳٫۷–۱۴٫۲MB | ۱۲۸MB | **۳۸–۴۰MB** |
| noopt | ۰ (خاموش) | — | **۳۴–۳۸MB** |

> 🔑 **RSS هر ورکر گرم ~۳۷–۴۶MB** (بستهٔ سطح). برای محاسبهٔ `pm.max_children`
> در PHP-FPM اشتراکی:
> `(RAM_سهمیه − RAM_DB/سیستم) ÷ ~۴۵MB`. روی پلن ۱GB با ~۵۱۲MB آزاد →
> **~۱۰–۱۲ ورکر هم‌زمان**. (در fpm واقعی OPcache SHM **بین ورکرها مشترک** است،
> پس ورکر حاشیه‌ای ارزان‌تر از این هم می‌شود — `php -S` هر worker یک SHM جدا
> دارد که بدبینانه است.)

---

## ۵. نتایج — حافظه و سقف منابع

### ۵٫۱ حافظهٔ هر درخواست HTTP معمول

| سناریو | اوج heap | فایل include | کلاس declare |
|---|---|---|---|
| `/time` | **۴MB** | ۳۳۵ | ~۳۸۲ |
| `/` (view) | **۴MB** | ۳۳۹ | ~۴۴۰ |
| `/blog` (db) | **۴–۶MB** | ۳۶۸ | ~۴۶۴ |

> یک درخواست وب معمولی تَوان **۴–۶MB** heap مصرف می‌کند. با `memory_limit=128M`
> **بیش از ۲۰× حاشیهٔ امن** وجود دارد. حتی `minimum` برای ۹۵٪ سایت‌های کوچک/متوسط کافی است.

### ۵٫۲ حافظهٔ اوج در برابر سقف (چرا ۱۲۸/۲۵۶/۵۱۲)

| سطح | memory_limit | HTTP معمول | گزارش/ایمپورت | پوشش |
|---|---|---|---|---|
| حداقل | ۱۲۸M | ۴–۶MB (۲۰× حاشیه) | تا ~۴۰–۵۰k رکورد | سایت کوچک/متوسط، وبلاگ، فروشگاه سبک |
| ایده‌آل | ۲۵۶M | ۴–۶MB (۴۰× حاشیه) | تا ~۱۰۰k رکورد | اکثر اپ‌های production، CRM/LMS/ERP |
| سنگین | ۵۱۲M | ۴–۶MB (۸۰× حاشیه) | تا ~۱۸۰k+ رکورد | BI/گزارش سنگین، ایمپورت بزرگ، BPMS پرحجم |

### ۵٫۳ منحنی رشد حافظهٔ زیرسیستم‌ها (`memory-ceiling.php`)

**موتور گزارش (Report: dataset + groupBy + pivot + تاریخ جلالی):**

| ردیف | اوج حافظه | زمان |
|---|---|---|
| ۱٬۰۰۰ | ۲MB | ۲۴ms |
| ۵٬۰۰۰ | ۱۴MB | ۸۲ms |
| ۲۰٬۰۰۰ | ۵۰MB | ۳۲۳ms |
| ۵۰٬۰۰۰ | **۱۲۶MB** ← لبهٔ ۱۲۸M | ۸۸۳ms |
| ۱۰۰٬۰۰۰ | **۲۵۱MB** ← لبهٔ ۲۵۶M | ۱٬۸۲۰ms |
| ۱۵۰٬۰۰۰ | ۲۸۵MB | ۱٬۸۵۳ms |

**سایر زیرسیستم‌ها (رشد خطی ~۱٫۴MB بر ۱k رکورد):**

| زیرسیستم | ۱۰k | ۵۰k | ۱۰۰k | ۲۰۰k |
|---|---|---|---|---|
| JSON encode | ۱۴MB | ۷۰MB | ۱۴۴MB | ۲۹۲MB |
| serialize | ۱۴MB | ۷۰MB | ۱۳۹MB | ۲۷۸MB |
| validate (فیلد) | — | ۸MB(۵k) | ۲۸MB(۲۰k) | ۶۴MB(۵۰k) |
| **pipeline** | ۸MB(۱۰۰k) | — | — | ۳۲MB(۵۰۰k) / ۶۴MB(۱M) |

> 🔑 **pipeline تَوان فوق‌العاده کم‌حافظه است** (۱M عنصر = ۶۴MB) → برای
> پردازش دسته‌ای بزرگ روی اشتراکی، Pipeline را به Report ترجیح دهید.

### ۵٫۴ اثبات اجرای سقف (memory_limit واقعاً محافظت می‌کند)

گزارش ۱۰۰k ردیف (نیاز ~۲۵۱MB) تحت هر سطح واقعی (`-c` بدون override):

| پروفایل | نتیجه |
|---|---|
| **minimum** (۱۲۸M) | ❌ OOM — اما **مدیریت‌شده**: `ErrorHandler->handleShutdown` خطا را شکار و تمیز گزارش می‌کند (کرش خام نه) |
| **ideal** (۲۵۶M) | ✅ موفق (۲۵۱MB) |
| **heavy** (۵۱۲M) | ✅ موفق — گزارش ۱۵۰k هم (۲۸۵MB) |

```
PHP Fatal error: Allowed memory size of 134217728 bytes exhausted
  → [ErrorException] توسط Tavaan\Engine\Error\ErrorHandler->handleShutdown
```

> این رفتار «شکست کنترل‌شده» مهم است: کاربر صفحهٔ خطای تمیز می‌بیند، نه
> سفید/کرش. برای بارهای >۵۰k روی سطح minimum، یا سطح را ارتقا دهید یا از
> `queue`/`pipeline` دسته‌بندی‌شده استفاده کنید.

### ۵٫۵ توان عملیاتی (Throughput) — پروفایل ideal

`php -S` با workerهای موازی، بار از `curl_multi` (خودِ بار هم CPU می‌خواهد):

| worker | همزمانی | `/time` RPS | `/time` p50 | `/blog` RPS | `/blog` p50 | خطا |
|---|---|---|---|---|---|---|
| ۱ | ۱ | **۸۲٫۶** | ۱۱٫۴ms | **۵۹٫۳** | ۱۵٫۵ms | **۰** |
| ۱ | ۴ | ۷۴٫۵ | ۴۹ms | ۵۸٫۰ | ۶۷ms | **۰** |
| ۴ | ۸ | ۷۱٫۵ | ۱۰۲ms | ۵۲٫۲ | ۱۳۶ms | **۰** |
| ۴ | ۱۶ | ۷۰٫۳ | ۲۱۱ms | ۵۶٫۲ | ۲۸۵ms | **۰** |

**تحلیل:**
- توان روی **~۷۰–۸۳ RPS** اشباع می‌شود چون sandbox فقط **۲ vCPU اشتراکی** دارد
  و تولیدکنندهٔ بار هم CPU مصرف می‌کند. افزودن worker از ۱→۴ توان را بالا
  **نبرد** (CPU-bound) — همان یافتهٔ ADR-0017: مقیاس‌پذیری فراتر از هسته بی‌فایده است.
- **صفر خطای transport و صفر 5xx** در همهٔ سطوح → پایداری کامل تحت بار.
- latency با افزایش همزمانی بالا می‌رود (صف روی ۲ هسته) — طبیعی و انتظارش می‌رفت.
- روی LiteSpeed واقعی با LSCache، صفحات کش‌شدنی (مثل `/blog`) چند برابر سریع‌ترند.

> **جمع‌بندی ظرفیت:** یک ورکر تَوان روی اشتراکی ~۶۰–۸۰ RPS پایدار می‌دهد
> (کف بدبینانه). با ۱۰–۱۲ ورکر (پلن ۱GB) → **~۶۰۰–۹۰۰ RPS تئوری** برای
> صفحات سبک، و با LSCache روی صفحات کش‌شدنی بسیار بیشتر. این برای یک سایت
> اشتراکی پربازدید کاملاً کافی است.

---

## ۶. سازگاری با هاست محدود (`disable_functions`)

پروفایل `restricted` این تابع‌ها را می‌بندد: `proc_open, popen, shell_exec,
exec, passthru, system, putenv, symlink, pcntl_*`. نتیجهٔ تست:

| بررسی | نتیجه |
|---|---|
| بوت کامل + `/time`/`/`/`/blog` (سرد و گرم، ۲۰۰ درخواست) | ✅ همه **۲۰۰** — بدون افت (p50 ۲٫۹–۸٫۵ms) |
| **سرور زندهٔ e2e** (پروفایل restricted، `php -S` پایدار) | ✅ `/`→۲۰۰، `/time`→۲۰۰، `/blog`→۲۰۰، `/no-such`→۴۰۴، **۵۰/۵۰ پایدار**، صفر خطای disable-related |
| `tvn doctor` | ✅ «محیط سالم است» + fallback خودکار افزونه‌ها |
| `putenv` بسته | ✅ تَوان هرگز `putenv` نمی‌زند (DOC-027: env بدون putenv) |
| `proc_open` بسته | ✅ مسیر وب به آن نیاز ندارد؛ فقط ابزارهای dev (test/lint/bench)، `queue:listen`، `sendmail` و `schedule` — همه fallback دارند |

**تابع‌هایی که ممکن است در اشتراکی بسته باشند و وضعیت تَوان:**

| تابع | کجا استفاده می‌شود | در مسیر وب؟ | fallback |
|---|---|---|---|
| `proc_open` | `queue:listen`, `schedule:run`, mail `sendmail`, ابزار dev | ❌ نه | ✅ queue درون‌فرآیندی، mail با SMTP، schedule با cron |
| `putenv` | — | ❌ نه | ✅ tَوان putenv-free است |
| `exec`/`shell_exec` | فقط ابزار dev (lint/bench) | ❌ نه | ✅ در وب لازم نیست |
| `pcntl_*` | queue worker recycling | ❌ نه | ✅ `max_requests`/memory-based recycle |
| `symlink` | backup (اختیاری) | ❌ نه | ✅ copy به‌جای link |

> ✅ **نتیجه:** مسیر HTTP تَوان (درخواست → پاسخ) **هیچ وابستگی سختی به تابع‌های
> بسته‌شده ندارد**. همهٔ مصرف‌ها در CLI/ابزار dev هستند که fallback دارند.

### ۶٫۱ fallback افزونه‌ها (`tvn doctor`)

تَوان برای افزونه‌های رایج در اشتراکی fallback خودکار دارد:

| افزونه | اگر نباشد |
|---|---|
| `intl` | → mbstring ( JalaliEngine + i18n) |
| `redis`/`memcached`/`apcu` | → cache:file |
| `pdo_mysql`/`pdo_pgsql` | → SQLite یا db:none |
| `gd`/`imagick` | → media بدون resize (ذخیرهٔ اصل) |
| `opcache` | → optimizer:none (کندتر اما کار می‌کند) |

---

## ۷. چک‌لیست استقرار روی هاست اشتراکی

```
☐ ۱. PHP ≥ 8.2 از پنل انتخاب شود (MultiPHP)
☐ ۲. php.ini طبق سطح مناسب تنظیم شود (examples/hosting/php-<tier>.ini)
       → در cPanel: «MultiPHP INI Editor»؛ فقط PHP_INI_PERDIR/USER در .user.ini
☐ ۳. کد آپلود شود (FTP/File Manager/git) — بدون نیاز به Composer
☐ ۴. document root به public/ اشاره کند (یا public_html → symlink/محتوای public)
☐ ۵. cp .env.example .env && php bin/tvn key:generate
☐ ۶. تنظیم DB در .env (mysql؛ یا sqlite برای بار سبک)
☐ ۷. php bin/tvn migrate --force
☐ ۸. php bin/tvn optimize   (کش config + autoload + route)
☐ ۹. مجوز نوشتن: storage/ و bootstrap/cache → 775 (یا chown کاربر)
☐ ۱۰. cron: */5 * * * * php /home/USER/tavaan/bin/tvn schedule:run
       (queue/schedule/GC session را پوشش می‌دهد)
☐ ۱۱. اگر میزبان اجازه داد: opcache.preload → examples/hosting/preload.php
☐ ۱۲. php bin/tvn doctor  → «محیط سالم است»
☐ ۱۳. اسموک: curl https://site/ و /time → ۲۰۰
```

### ۷٫۱ `.user.ini` وقتی ویرایش php.ini ممکن نیست

بسیاری از هاست‌ها فقط `.user.ini` (در document root) را می‌پذیرند. فقط
دستورهای `PHP_INI_PERDIR`/`PHP_INI_USER` آنجا اثر دارند:

```ini
; public/.user.ini  (یا public_html/.user.ini)
memory_limit = 256M
max_execution_time = 120
upload_max_filesize = 32M
post_max_size = 35M
max_input_vars = 5000
realpath_cache_size = 4096K
realpath_cache_ttl = 600
; ⚠️ opcache.* در .user.ini اثر ندارد (PHP_INI_SYSTEM) — از پنل/MultiPHP تنظیم کنید
```

یا معادل `.htaccess` (Apache/mod_php — کمتر رایج در LiteSpeed):

```apache
php_value memory_limit 256M
php_value upload_max_filesize 32M
php_value max_execution_time 120
```

### ۷٫۲ نکتهٔ `validate_timestamps=0` در اشتراکی

اگر OPcache را روی `validate_timestamps=0` (سطح ideal/heavy) بگذارید، پس از
**هر دیپلوی** باید OPcache را reset کنید (چون تغییر فایل را نمی‌بیند):
- cPanel/LiteSpeed: «Reset OPcache» در پنل، یا
- یک فایل `opcache_reset.php` موقت با `<?php opcache_reset();`، یا
- تغییر `opcache.validate_timestamps=1` موقتاً.

اگر reset در دسترس نیست، در سطح `minimum` بمانید (`validate_timestamps=1`).

---

## ۸. مقایسه با سایر فریمورک‌ها (الگوی صورت‌مسئله)

| نیاز | وردپرس (ایده‌آل) | لاراول (ایده‌آل) | سمفونی (ایده‌آل) | **تَوان (ایده‌آل)** |
|---|---|---|---|---|
| `memory_limit` | ۵۱۲M | ۵۱۲M | ۵۱۲M | **۲۵۶M** (سبک‌تر — HTTP ~۶MB) |
| `max_execution_time` | ۳۰۰ | ۳۰۰ | ۶۰ | **۱۲۰** |
| `upload_max_filesize` | ۶۴M | ۳۲M | ۲۰M | **۳۲M** |
| `opcache.memory_consumption` | ۵۱۲ | ۱۶۰ | ۲۵۶ | **۱۲۸** (footprint ~۲۲MB) |
| `opcache.max_accelerated_files` | ۲۰۰۰۰ | ۲۰۰۰۰ | ۲۰۰۰۰ | **۲۰۰۰۰** |
| `save_comments` | — | ۱ | ۰ (Attribute) | **۱** (docblock-DI) |
| Composer لازم؟ | نه | بله | بله | **نه** ✅ |
| resident runtime؟ | نه | نه | نه (اختیاری) | **نه** (ADR-0017) ✅ |
| بوت سرد | ~۱۵۰ms | ~۱۰۰ms | ~۸۰ms | **~۸۰–۱۰۵ms** |
| p50 گرم (صفحه سبک) | ~۱۵–۳۰ms | ~۲۰–۴۰ms | ~۱۰–۲۰ms | **~۲٫۵–۳٫۵ms** ✅ |

> تَوان با **حافظهٔ کمتر** (۲۵۶M در برابر ۵۱۲M) و **p50 بسیار سریع‌تر**
> (~۳ms در برابر ۱۰–۴۰ms) و **بدون نیاز به Composer/resident runtime**، برای
> هاست اشتراکی **بهینه‌تر** از سه مرجع رایج است. (اعداد سایر فریمورک‌ها تخمینی
> از مستندات عمومی‌اند؛ تَوان اندازه‌گیری واقعی این سند است.)

---

## ۹. جمع‌بندی — آیا تَوان روی هاست اشتراکی «پاسخگویی مناسب» دارد؟

**بله — با اندازه‌گیری اثبات شد:**

| معیار | مقدار | قضاوت |
|---|---|---|
| حافظهٔ هر درخواست وب | ۴–۶MB | ✅ حتی زیر `memory_limit=64M` هم جا می‌شود |
| RSS هر ورکر گرم | ۳۷–۴۶MB | ✅ ۱۰–۱۲ ورکر در پلن ۱GB |
| p50 پایدار (OPcache) | ۲٫۵–۳٫۵ms (سبک)، ۶–۹ms (DB) | ✅ پاسخ آنی |
| throughput | ~۶۰–۸۳ RPS/ورکر، ۰ خطا | ✅ پایدار تحت بار |
| footprint در OPcache | ۱۳–۳۹MB | ✅ حتی `memory_consumption=64` کافی است |
| سازگاری `disable_functions` | ۱۰۰٪ مسیر وب | ✅ بدون افت |
| fallback افزونه‌ها | intl/redis/gd/mysql/opcache | ✅ `tvn doctor` تأیید |
| نیاز به Composer/daemon | ندارد | ✅ آپلود و اجرا |
| شکست حافظه | مدیریت‌شده (ErrorHandler) | ✅ کرش خام نه |

**توصیهٔ نهایی:** برای اکثر سایت‌ها روی هاست اشتراکی، **سطح «ایده‌آل»**
(`php-ideal.ini`، `memory_limit=256M`) نقطهٔ تعادل است. سایت‌های کوچک/سبک با
**«حداقل»** (۱۲۸M) کاملاً راحت‌اند. فقط اپ‌های BI/ایمپورت بزرگ به **«سنگین»**
(۵۱۲M) نیاز دارند. **OPcache در همهٔ سطوح الزامی است** (۳× سرعت، نصف حافظه).

---

## ۱۰. بازتولید اندازه‌گیری‌ها

```bash
# ۱) ماتریس کامل (سرد+گرم) همهٔ پروفایل‌ها × ۳ سناریو
php tools/scripts/shared-host-bench.php --requests 200

# ۲) RSS ورکر گرم هر پروفایل
php tools/scripts/worker-rss.php

# ۳) سقف حافظهٔ زیرسیستم‌ها (رشد خالص)
TVN_MEM_PROBE_UNLIMITED=1 php tools/scripts/memory-ceiling.php report 100000

# ۴) اجرای سقف واقعی (اثبات OOM مدیریت‌شده در ۱۲۸M)
php -c examples/hosting/php-minimum.ini tools/scripts/memory-ceiling.php report 100000

# ۵) تجزیهٔ بوت‌استرپرها
php tools/scripts/boot-profile.php

# ۶) throughput (پروفایل ideal)
php -c examples/hosting/php-ideal.ini -S 127.0.0.1:8080 -t public public/index.php &
php tools/scripts/load-test.php http://127.0.0.1:8080/time 500 1
```

خروجی خام همهٔ اندازه‌گیری‌ها در `storage/temp/shared-host-evidence.json` تجمیع می‌شود.

---

*پایان DOC-134 — آخرین به‌روزرسانی: ۲۰۲۶-۱۰-۲۷ (اندازه‌گیری روی PHP 8.2.35)*
