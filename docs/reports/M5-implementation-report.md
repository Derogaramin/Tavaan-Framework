# گزارش پیاده‌سازی M5 — فریمورک تَوان (ارتباطی و زیرساخت)

**تاریخ:** ۲۰۲۶-۱۰-۱۶ • **نگارش:** `0.5.1` • **مخزن:** `Derogaramin/Tavaan-Framework`
**کامیت پیاده‌سازی:** `1c6f85d` (M5 — نسخهٔ 0.5.0) • **کامیت Hotfix:** محافظ بازگشت (نسخهٔ 0.5.1 — همین گزارش)
**اسناد بالادستی:** DOC-120 (`docs/08-roadmap/milestones.md`) و DOC-121 (`docs/08-roadmap/agent-backlog.md`)

---

## ۱. بررسی وضعیت: کدنویسی M5 تا کجا انجام شده بود؟

پاسخ کوتاه: **هر ۸ تیکت M5 به‌طور کامل پیاده‌سازی، تست و کامیت شده بود** (`1c6f85d`) و مجموعهٔ تست در زمان کامیت سبز بود (۶۹۵ تست / ۳۴۰۶ assertion). خطای گزارش‌شدهٔ «Maximum call stack size exceeded» نشانهٔ ناتمام‌بودن کدنویسی **نبود**؛ یک **نقص نهفته (latent defect)** در دو نقطه از کد تحویل‌شده بود که فقط با ورودی‌های خاص (دادهٔ دوری/عمیق یا زمان‌بندی `schedule:run`) فعال می‌شد و هیچ‌کدام در پوشش تست‌های اولیه نبود. بخش ۲ جزئیات کامل ریشه‌یابی و رفع را دارد.

### وضعیت تیکت‌ها (DOC-121 §6)

| تیکت | موتور | سند | وضعیت | تست |
|---|---|---|---|---|
| TVN-0061 | **Queue** | DOC-061 `docs/03-engines/queue.md` | ✅ کامل (+hotfix 0.5.1) | ۳۴ |
| TVN-0062 | **Schedule** | DOC-062 `docs/03-engines/schedule.md` | ✅ کامل (+hotfix 0.5.1) | ۲۲ |
| TVN-0063 | **Filesystem** | DOC-063 `docs/03-engines/filesystem.md` | ✅ کامل | ۲۳ |
| TVN-0064 | **HttpClient** | DOC-064 `docs/03-engines/http-client.md` | ✅ کامل | ۲۲ |
| TVN-0065 | **Mail** | DOC-065 `docs/03-engines/mail.md` | ✅ کامل | ۱۷ |
| TVN-0066 | **Notification** | DOC-066 `docs/03-engines/notification.md` | ✅ کامل | ۱۶ |
| TVN-0074 | **Media** | DOC-074 `docs/03-engines/media.md` | ✅ کامل | ۱۳ |
| TVN-0068 | **Backup** | DOC-068 `docs/03-engines/backup.md` | ✅ کامل | ۱۱ |

**حجم تحویل M5:** ~۱۹٬۲۰۰ خط کد منبع در `engines/*/src/` + ۳۸۲ خط قرارداد در `core/Contracts/{Queue,Filesystem,Mail,Notification}` + ۸ فایل `module.json` + ۴ migration + ۱۶ فایل زبان (fa/en) + ۹ دستور CLI + ۸ Facade. صفر وابستگی بیرونی (مطابق DOC-001).

---

## ۲. خطای «Maximum call stack size exceeded» — ریشه‌یابی و رفع

### ۲.۱ بازبینی اولیه (همه سبز)

| بررسی | نتیجه |
|---|---|
| `php bin/tvn test` | ✅ ۶۹۵ تست سبز (پیش از رفع) |
| `tvn about` / `tvn optimize` / `tvn migrate:status` / `queue:work --once` | ✅ سالم |
| `tvn serve` + درخواست HTTP | ✅ 200 OK (صفحهٔ خوش‌آمد فارسی) |
| حلقهٔ symlink در پیمایش دایرکتوری (Filesystem/Backup) | ✅ ایمن (RecursiveIteratorIterator دنبال نمی‌کند) |

### ۲.۲ بازتولید نظام‌مند — ۴ سناریوی شکست در Queue Serializer

| # | سناریو | رفتار پیش از رفع | ریشه |
|---|---|---|---|
| ۱ | شیء با ارجاع دوری: `$job->ref = $job` → `Queue::push` | 💥 **OOM ~۸۵۰MB** (یا stack overflow در محیط‌های کم‌حافظه/با Xdebug → دقیقاً پیام گزارش‌شده) | `Serializer::prepare` بدون تشخیص دور، بی‌نهایت بازگشت می‌کرد |
| ۲ | زنجیره به خود: `$job->chain([$job])` | 💥 **OOM** | `chainProp` → `createJobPayload` → `chainProp` … بی‌پایان (دور متقابل a→b→a نیز) |
| ۳ | آرایهٔ ۵۰۰۰ سطحی در payload | ⚠️ `JsonException` خام («Maximum stack depth exceeded»)؛ در عمق‌های بیشتر 💥 stack overflow داخل خودِ `prepare` | نبود سقف عمق |
| ۴ | آرایهٔ دوری با ارجاع: `$arr['self'] = &$arr` | 💥 **OOM** | `prepare` روی ارجاع آرایه‌ای بی‌نهایت بازگشت می‌کرد |

**چرا کشنده بود؟** OOM/stack-overflow در PHP از نوع `Error` کشنده و عملاً **غیرقابل‌گیری** mid-recursion است — worker صف را در میانهٔ job می‌کشت (بدون failed-job recording تمیز) و در سناریوی رویداد/زنجیره، کل فرآیند dispatch سقوط می‌کرد.

### ۲.۳ سناریوی پنجم: خودبازگشتی `schedule:run`

اگر کاربر در `routes/console.php` خودِ `schedule:run` را زمان‌بندی می‌کرد:
- حالت درون‌فرآیندی → `runCommand` → `kernel->resolve('schedule:run')` → `handle` → `run()` → `runCommand` → … **بازگشت بی‌پایان → دقیقاً «Maximum call stack size exceeded»**
- حالت background → هر اجرا یک `schedule:run` جدید زایش می‌داد (**انفجار فرآیند**)

### ۲.۴ رفع‌ها (نگارش 0.5.1)

**`engines/queue/src/Serializer.php`:**

| محافظ | مکانیزم |
|---|---|
| تشخیص ارجاع دوری اشیا | `SplObjectStorage` **مسیر-محور** (attach پیش از بازگشت / detach در `finally`) — گراف لوزی (یک شیء در دو شاخه، بدون دور) همچنان مجاز است؛ **مثبت کاذب ندارد** |
| سقف عمق | `MAX_DEPTH = 64` در هر دو سمت `prepare` و `restore` — دورِ آرایه‌های ارجاعی و payload عمیق هر دو با **`QueueException` تمیز** رد می‌شوند |
| تشخیص زنجیرهٔ دوری | ردیابی `spl_object_id` اعضای زنجیره در `buildJobPayload`/`chainProp` — خود-زنجیره و دور متقابل هر دو در اولین برخورد رد می‌شوند |
| پیچیدن `JsonException` | `encode` → `queue.unserializable` و `decodePayload` → `queue.invalid_payload` (JSON خراب/عمیق دیگر استثنا خام نشت نمی‌دهد) |
| سمت بازخوانی (امنیت) | payload **دستکاری‌شده** با هزاران سطح تودرتویی دیگر نمی‌تواند stack را در `restore` بشکند (سقف عمق + `json_decode` با عمق صریح ۵۱۲) |

**`engines/schedule/src/ScheduleManager.php`:**
- `runCommand` پیش از هر شاخه‌ای (background/درون‌فرآیندی) دستور `schedule:run` را رد می‌کند: `['failed', 1, 'schedule:run cannot be scheduled (infinite recursion guard)']` — رویداد در خروجی `schedule:run` و لاگ به‌شکل واضح شکست‌خورده گزارش می‌شود.

### ۲.۵ تست‌های رگرسیون (۹ تست جدید + fixture `CycleJob`)

`engines/queue/tests/Feature/QueueTest.php`:
1. `testCircularObjectReferenceRejected` — ارجاع دوری شیء → `queue.unserializable`
2. `testCircularArrayReferenceRejected` — دور آرایه‌ای با ارجاع → `queue.unserializable`
3. `testDeepNestingRejectedOnPrepare` — ۱۰۰ سطح رد می‌شود؛ **۶۰ سطح مجاز می‌ماند** (بدون مثبت کاذب)
4. `testSharedObjectGraphIsNotFalsePositive` — گراف لوزی موفق سریال می‌شود (هر دو شاخه در payload)
5. `testSelfChainRejected` — `$job->chain([$job])` → `queue.unserializable`
6. `testMutualChainCycleRejected` — a→b→a → `queue.unserializable`
7. `testRestoreRejectsDeepTamperedPayload` — payload دستکاری‌شدهٔ ۱۰۰ سطحی در `restoreCommand` → `queue.invalid_payload`
8. `testDecodePayloadRejectsMalformedJson` — JSON خراب → `queue.invalid_payload` (نه `JsonException` خام)

`engines/schedule/tests/Feature/ScheduleTest.php`:
9. `testScheduleRunCannotBeScheduled` — زمان‌بندی `schedule:run` → `failed` با پیام `recursion` (بدون بازگشت)

**نتیجهٔ بازتولید پس از رفع:** هر ۴ سناریوی بخش ۲.۲ که OOM/stack-overflow می‌دادند، اکنون `QueueException: queue.unserializable` تمیز و قابل‌گیری برمی‌گردانند (راستی‌آزمایی شد).

---

## ۳. خلاصهٔ موتورهای تحویل‌شده در M5

### TVN-0061 — Queue (`engines/queue/`) • Facade `Queue`
- **۵ درایور:** sync / database / file / redis / null؛ redis با **کلاینت RESP خودنوشته** روی stream + Lua scripts اتمیک (بدون ext-redis) و `RedisClientInterface` برای fake.
- **Pop اتمیک** با `FOR UPDATE SKIP LOCKED` + بازیابی reservedهای گیرکرده (`retry_after`).
- **Worker مقاوم:** retry با backoff نمایی/فهرستی، timeout با `pcntl_alarm` (+ fallback cooperative)، graceful shutdown (SIGTERM/SIGINT)، recycle حافظه، `queue:restart`.
- **Batch** (finally/allowFailures/cancel) + **Chain** (توقف در شکست) + Delayed + **Rate Limiting** + Failed Jobs + **DLQ** اختیاری.
- 🔒 **سریال‌سازی فقط JSON + Allowlist** (هرگز `unserialize` باز)؛ مدل‌های ORM فقط «کلاس+کلید»؛ ♻️ محافظ بازگشت (بخش ۲).
- رویدادهای `ShouldQueue` با roundtrip کامل (`dispatchNow` ضد requeue).
- **CLI:** `queue:work/listen/restart/retry/failed/flush/prune-failed/monitor` • **جداول:** `tvn_jobs` `tvn_failed_jobs` `tvn_job_batches`.

### TVN-0062 — Schedule (`engines/schedule/`) • Facade `Schedule`
- **Cron parser خودنوشته** (۵ فیلد، list/range/step، `7`=یکشنبه، OR کلاسیک DOM/DOW — منطبق بر بردارهای مرجع).
- Fluent کامل (everyMinute…yearly/cron/at/weeklyOn/…)، **withoutOverlapping** (mutex اتمیک `cache->add`)، **onOneServer**، timezone per-event، between/when/skip، runInBackground، evenInMaintenanceMode، onSuccess/onFailure، output(path)، `nextRunDates`.
- چهار نوع وظیفه: command (درون‌فرآیندی با ضبط خروجی) / call / job→queue / exec • 🔒 محافظ خودبازگشتی (بخش ۲.۳).
- **CLI:** `schedule:run` `schedule:list` • `routes/console.php`.

### TVN-0063 — Filesystem (`engines/filesystem/`) • Facade `Storage`
- **۴ درایور:** local / memory / s3 / ftp؛ 🔒 Path Sandbox (رد `..`/null-byte + realpath ضد symlink-escape) + Atomic Write (tmp+rename+LOCK_EX).
- 🔒 **S3 SigV4 خودنوشته بدون SDK** — سبز روی **AWS reference vectors** (GET/PUT Object)؛ S3-compatible (Minio/ArvanCloud) با endpoint/path-style.
- presigned `temporaryUrl` (S3) و HMAC (local)، list با continuation-token، readStream/writeStream با حافظهٔ ثابت، putFile/download/`storage:link`.

### TVN-0064 — HttpClient (`engines/http-client/`) • Facade `Http`
- Factory fluent کامل + multipart/attach + timeout + retry (backoff نمایی+jitter؛ فقط timeout/5xx/429 با احترام به Retry-After).
- **Circuit Breaker** روی cache (۵ خطا/۶۰s → open ۳۰s → half-open).
- 🔒 **SSRF Guard** (private/loopback/link-local/**cloud metadata**/port غیرمعمول + fail-closed DNS) با **re-check در هر redirect hop**.
- **Pool موازی** (curl_multi + fallback ترتیبی)، `Http::fake()`/assertSent/sequences، transport curl→stream (Compat).

### TVN-0065 — Mail (`engines/mail/`) • Facade `Mail`
- **SMTP client خالص خودنوشته** (STARTTLS/SSL/AUTH LOGIN/multi-line/🔒 dot-stuffing/timeout اجباری/سوکت تزریقی برای تست با socket-pair).
- **MIME Builder** (mixed→alternative→related، RFC 2047 فارسی، Bcc هرگز در هدر نشت نمی‌کند) • **۶ transport** (smtp/sendmail/mail/log/array/**ses با SigV4**).
- `Mailable` کامل + `Mail::queue/later` (پیوست base64 JSON-safe) + `fake()`/assertSent + 🔒 محافظ Header Injection.

### TVN-0066 — Notification (`engines/notification/`) • Facade `Notification`
- **۸ کانال:** mail/sms/database/broadcast/push/webhook/telegram/bale/eitaa + کانال سفارشی با extend.
- **۵ درگاه SMS ایرانی** (کاوه‌نگار/ملی‌پیامک/قاصدک/SMS.ir/فراز) + LogSmsGateway؛ 🔒 mask شماره در لاگ (`0912***4567`).
- **In-App** (`tvn_notifications`: unread/markAsRead/…)، `AnonymousNotifiable` (`Notification::route(...)->notify(...)`)، trait `RoutesNotifications` (`$user->notify()`)، صف (SendQueuedNotification)، 🔒 Rate Limit per-user-per-hour ضد SMS Bombing، جداسازی شکست کانال‌ها، `fake()`/assertSentTo.

### TVN-0074 — Media (`engines/media/`) • Facade `Media`
- 🔒 **جریان آپلود امن ۷ مرحله‌ای** (DOC-074 §2): size/extension → **MagicBytes MIME واقعی** → تطبیق extension↔MIME → **polyglot scan** (`<?php`) → **Image Bomb** (سقف ابعاد از هدر، بدون decode) → rename تصادفی → ثبت + **dedup SHA-256**.
- `Image` fluent (resize/crop/thumbnail/watermark/convert/rotate/flip/exif) با درایور auto→imagick→gd→**none** (`EngineCapabilityException`؛ ذخیره+metadata همیشه کار می‌کند)، **variants**، EXIF **سانیتیزه (بدون GPS)**، re-encode ضد polyglot • جدول `tvn_media`.

### TVN-0068 — Backup (`engines/backup/`) • Facade `Backup`
- **DB Dump خالص بدون mysqldump** (schema از sqlite_master/SHOW CREATE + داده **chunked با cursor → حافظهٔ ثابت**، escape با PDO::quote).
- **ZipReader خودنوشته** (EOCD→CD، Store/Deflate، 🔒 CRC-32) + ZipWriterPure • 🔒 **AES-256-GCM با کلید جدا از APP_KEY** (`TVN_BACKUP_KEY`، AAD).
- Manifest/verify (hash+decrypt)/restore (🔒 force + maintenance mode خودکار + بررسی traversal)/prune با retention • **CLI:** `backup:run/list/verify/restore/prune` • disk اختصاصی `backups`.

---

## ۴. تغییرات زیرساختی بین-موتوری در M5

| تغییر | محل | دلیل |
|---|---|---|
| `forUpdate()/forUpdateSkipLocked()/sharedLock()` | QueryBuilder/Grammar/contract | Pop اتمیک صف (SQLite: clause خالی — اتمیک با تراکنش) |
| `EventDispatcher::dispatchNow()` | core/Events | اجرای رویداد صف‌روند بدون حلقهٔ requeue |
| `LogManager::default()` دینامیک | engines/log | تغییر `log.default` در runtime (پیش‌تر در ساخت ثابت بود) |
| disk `backups` | engines/filesystem/config | مقصد آرشیوهای Backup |
| `S3Signer` عمومی (service param) | engines/filesystem | بازاستفادهٔ SES transport از همان SigV4 |

---

## ۵. راستی‌آزمایی نهایی (پس از hotfix 0.5.1)

| Gate | نتیجه |
|---|---|
| `php bin/tvn test` | ✅ **۷۰۴ تست / ۳۴۲۰ assertion — ۰ شکست** (پیش از رفع: ۶۹۵/۳۴۰۶) |
| `php -l` روی همهٔ فایل‌های تغییریافته | ✅ پاک |
| بازتولید ۴ سناریوی stack-overflow | ✅ همه `QueueException` تمیز |
| `tvn about` | ✅ ۳۹/۳۹ موتور — نگارش **0.5.1** |
| `tvn optimize` / `migrate:status` / `queue:work --once` / `serve`+HTTP | ✅ سالم |

---

## ۶. محدودیت‌های شناخته‌شده (مستند و عمدی)

- **FileQueue:** هم‌پوشانی workerهای پس‌زمینه به TTL فایل اتکا دارد (بدون قفل جهانی) — برای استقرار تک‌سرور کافی است.
- **Pool موازی:** مسیر واقعاً موازی فقط با curl (در این سندباکس curl نصب نیست → fallback ترتیبی تست می‌شود).
- **S3/FTP/SES:** کد کامل است ولی بدون سرور زنده فقط با fake/بردارهای مرجع AWS تست شده.
- **ZipWriterPure:** آرشیو را در حافظه buffer می‌کند (خودِ DB dump حافظهٔ ثابت است).
- **Image با درایور none:** عملیات تصویری `EngineCapabilityException` می‌دهد (gd/imagick نصب نیست) — ذخیره، metadata و dedup همیشه فعال‌اند.
- **MAX_DEPTH=64:** payloadهای صف عمیق‌تر از ۶۴ سطح عمداً رد می‌شوند (برای jobهای واقعی بسیار سخاوتمندانه؛ در صورت نیاز قابل تغییر در `Serializer::MAX_DEPTH`).

---

## ۷. گام بعدی

طبق DOC-121 §7 (الگوریتم انتخاب تیکت): **فاز M6 — سرویس‌ها** با ۶ تیکت:
API (TVN-0044) • Observability (TVN-0067) • Search (TVN-0052) • Tenancy (TVN-0082) • FeatureFlag (TVN-0075) • Realtime (TVN-0045)
