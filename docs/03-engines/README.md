# DOC-030 — رجیستری موتورها (Engine Registry) 🔒🧠

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-030 |
| وضعیت | Approved v1.0 |
| نقش | فهرست مرجع همهٔ موتورها، وابستگی‌ها، ترتیب ساخت و وضعیت |
| تعریف موتور | کتابخانهٔ خودنوشتهٔ کامل (معادل پکیج Composer) که **صفر وابستگی خارجی** دارد |

---

## ۱. تعریف و آناتومی موتور

> **موتور (Engine)** = یک کتابخانهٔ مستقل و خودبسنده که یک «قابلیت» را کامل ارائه می‌دهد و
> توسط هسته از طریق `ServiceProvider` ثبت می‌شود. تفاوت آن با پکیج Composer این است که
> **کاملاً بازنویسی و خودکفا** شده و هیچ وابستگی خارجی ندارد.

هر موتور **باید** این ۷ قلم را داشته باشد (قاعدهٔ R6 در README):

| # | قلم | مسیر | الزامی |
|---|---|---|---|
| 1 | Provider | `src/<Name>ServiceProvider.php` | 🔒 |
| 2 | Contract | `core/Contracts/<Area>/*Interface.php` | 🔒 |
| 3 | پیاده‌سازی | `src/**` | 🔒 |
| 4 | Config | `config/<name>.php` | 🔒 |
| 5 | Lang | `lang/{fa,en}/<name>.php` | 🔒 |
| 6 | Tests | `tests/{Unit,Feature,Benchmark}` | 🔒 |
| 7 | Docs + Manifest | `docs/03-engines/<name>.md` + `module.json` | 🔒 |

اختیاری: `migrations/`, `views/`, `routes/`, `composer.json` (انتشار), `examples/`, `benchmarks/`.

---

## ۲. رجیستری کامل (۴۹ موتور)

### ۲٫۱ دستهٔ هسته (Kernel Engines) — P0

| # | موتور | Namespace | DOC | وابستگی | توضیح یک‌خطی |
|---|---|---|---|---|---|
| 1 | `container` | `Tavaan\Engine\Container` | [DOC-031](container.md) | — | کانتینر DI با Auto-Wiring |
| 2 | `config` | `Tavaan\Engine\Config` | [DOC-032](config.md) | container | مخزن تنظیمات + ENV |
| 3 | `event` | `Tavaan\Engine\Event` | [DOC-033](event.md) | container, config | رویداد‌محور + Outbox |
| 4 | `pipeline` | `Tavaan\Engine\Pipeline` | [DOC-034](pipeline.md) | container | خط لولهٔ Middleware |
| 5 | `error` | `Tavaan\Engine\Error` | [DOC-035](error.md) | log, event | مدیریت خطا + کدها |
| 6 | `log` | `Tavaan\Engine\Log` | [DOC-036](log.md) | config | لاگ ساختاریافته + کانال |
| 7 | `optimizer` | `Tavaan\Engine\Optimizer` | [DOC-037](optimizer.md) | همه | کش Config/Route/View/ClassMap |
| 8 | `module` | `Tavaan\Engine\Module` | [DOC-038](module.md) | container, config | کشف/ثبت ماژول‌ها |

### ۲٫۲ دستهٔ وب (Web Engines) — P0/P1

| # | موتور | Namespace | DOC | وابستگی | توضیح |
|---|---|---|---|---|---|
| 9 | `http` | `Tavaan\Engine\Http` | [DOC-039](http.md) | pipeline, event, error | Request/Response/Kernel |
| 10 | `router` | `Tavaan\Engine\Http\Routing` | [DOC-040](router.md) | http | مسیریاب با Compiled Trie |
| 11 | `session` | `Tavaan\Engine\Session` | [DOC-041](session.md) | http, crypt, cache | نشست با ۵ درایور |
| 12 | `cookie` | `Tavaan\Engine\Http\Cookie` | [DOC-042](cookie.md) | http, crypt | کوکی رمزنگاری‌شده |
| 13 | `view` | `Tavaan\Engine\View` | [DOC-043](view.md) | event, filesystem, i18n | Template Engine `.tv.php` |
| 14 | `api` | `Tavaan\Engine\Api` | [DOC-044](api.md) | http, validation | Resource/Versioning/OpenAPI |
| 15 | `realtime` | `Tavaan\Engine\Realtime` | [DOC-045](realtime.md) | event, http, queue | SSE + WebSocket |
| 16 | `cors` | `Tavaan\Engine\Http\Cors` | [DOC-046](cors.md) | http | CORS + Preflight |

### ۲٫۳ دستهٔ داده (Data Engines) — P0

| # | موتور | Namespace | DOC | وابستگی | توضیح |
|---|---|---|---|---|---|
| 17 | `database` | `Tavaan\Engine\Database` | [DOC-047](database.md) | config, event, log | PDO Wrapper + Query Builder |
| 18 | `schema` | `Tavaan\Engine\Database\Schema` | [DOC-048](schema.md) | database | Blueprint + DDL Grammar |
| 19 | `migration` | `Tavaan\Engine\Database\Migration` | [DOC-049](migration.md) | schema, database | مهاجرت + Lock + Rollback |
| 20 | `orm` | `Tavaan\Engine\Database\ORM` | [DOC-050](orm.md) | database, event | Active Record + Repository |
| 21 | `validation` | `Tavaan\Engine\Validation` | [DOC-051](validation.md) | i18n, database | ۸۰+ قاعده + FormRequest |
| 22 | `search` | `Tavaan\Engine\Search` | [DOC-052](search.md) | database, event | Full-text + Facet + Suggest |
| 23 | `pagination` | (در `database`) | [DOC-053](pagination.md) | database | Page/Cursor/Keyset |

### ۲٫۴ دستهٔ امنیت و هویت (Security Engines) — P0

| # | موتور | Namespace | DOC | وابستگی | توضیح |
|---|---|---|---|---|---|
| 24 | `crypt` | `Tavaan\Engine\Crypt` | [DOC-054](crypt.md) | config | AES-256-GCM + HKDF |
| 25 | `hash` | `Tavaan\Engine\Hash` | [DOC-055](hash.md) | config | Argon2id/Bcrypt |
| 26 | `auth` | `Tavaan\Engine\Auth` | [DOC-056](auth.md) | session, hash, crypt, event | Guard/Token/MFA/OTP |
| 27 | `rbac` | `Tavaan\Engine\Rbac` | [DOC-057](rbac.md) | auth, database, cache | نقش/مجوز/Gate/Policy |
| 28 | `audit` | `Tavaan\Engine\Audit` | [DOC-058](audit.md) | event, database, log | ممیزی با زنجیرهٔ هش |
| 29 | `security` | `Tavaan\Engine\Security` | [DOC-059](security.md) | http, crypt | CSRF/RateLimit/Header/Sanitizer/SSRF |

### ۲٫۵ دستهٔ زیرساخت (Infrastructure Engines) — P0/P1

| # | موتور | Namespace | DOC | وابستگی | توضیح |
|---|---|---|---|---|---|
| 30 | `cache` | `Tavaan\Engine\Cache` | [DOC-060](cache.md) | config, event | ۶ درایور + Tag + Lock |
| 31 | `queue` | `Tavaan\Engine\Queue` | [DOC-061](queue.md) | database, cache, event, log | صف + Worker + Batch |
| 32 | `schedule` | `Tavaan\Engine\Schedule` | [DOC-062](schedule.md) | console, cache, queue | Cron خودنوشته |
| 33 | `filesystem` | `Tavaan\Engine\Filesystem` | [DOC-063](filesystem.md) | config | Local/S3/FTP + Stream |
| 34 | `http-client` | `Tavaan\Engine\HttpClient` | [DOC-064](http-client.md) | event, cache | کلاینت HTTP + Retry + SSRF Guard |
| 35 | `mail` | `Tavaan\Engine\Mail` | [DOC-065](mail.md) | view, queue, event | SMTP/MIME خودنوشته |
| 36 | `notification` | `Tavaan\Engine\Notification` | [DOC-066](notification.md) | mail, queue, database | چندکاناله (Mail/SMS/Push/InApp) |
| 37 | `observability` | `Tavaan\Engine\Observability` | [DOC-067](observability.md) | event, log, http | Metrics + Trace + Health |
| 38 | `backup` | `Tavaan\Engine\Backup` | [DOC-068](backup.md) | database, filesystem, crypt | پشتیبان رمزنگاری‌شده |

### ۲٫۶ دستهٔ بومی‌سازی (Localization Engines) — P0

| # | موتور | Namespace | DOC | وابستگی | توضیح |
|---|---|---|---|---|---|
| 39 | `i18n` | `Tavaan\Engine\I18n` | [DOC-069](i18n.md) | config, cache | ترجمه + Plural + Locale |
| 40 | `jalali` | `Tavaan\Engine\I18n\Jalali` | [DOC-070](jalali.md) | i18n | تقویم جلالی/قمری دقیق |

### ۲٫۷ دستهٔ دامنه (Domain Engines) — P1/P2

| # | موتور | Namespace | DOC | وابستگی | توضیح |
|---|---|---|---|---|---|
| 41 | `workflow` | `Tavaan\Engine\Workflow` | [DOC-071](workflow.md) | event, database, rbac | BPM/State Machine/Saga |
| 42 | `report` | `Tavaan\Engine\Report` | [DOC-072](report.md) | database, filesystem | Dataset/Pivot/Export |
| 43 | `payment` | `Tavaan\Engine\Payment` | [DOC-073](payment.md) | http-client, database, event | درگاه‌های ایرانی/بین‌المللی |
| 44 | `media` | `Tavaan\Engine\Media` | [DOC-074](media.md) | filesystem, crypt | آپلود + پردازش تصویر |
| 45 | `feature-flag` | `Tavaan\Engine\FeatureFlag` | [DOC-075](feature-flag.md) | cache, rbac | پرچم قابلیت + Targeting |

### ۲٫۸ دستهٔ ابزار (Tooling Engines) — P1

| # | موتور | Namespace | DOC | وابستگی | توضیح |
|---|---|---|---|---|---|
| 46 | `console` | `Tavaan\Engine\Console` | [DOC-076](console.md) | container, event | CLI Framework |
| 47 | `scaffold` | `Tavaan\Engine\Scaffold` | [DOC-077](scaffold.md) | console, filesystem | make:* Generator |
| 48 | `admin` | `Tavaan\Engine\Admin` | [DOC-078](admin.md) | view, rbac, orm | CRUD Generator + Panel |
| 49 | `plugin` | `Tavaan\Engine\Plugin` | [DOC-079](plugin.md) | module, filesystem, crypt | پلاگین ثالث با Sandbox |
| 50 | `test` | `Tavaan\Engine\Test` | [DOC-084E](test.md) | همه | Test Framework خودنوشته |
| 51 | `upgrade` | `Tavaan\Engine\Upgrade` | [DOC-081E](upgrade.md) | console, database | ارتقای نسخه + Data Patch |
| 52 | `tenancy` | `Tavaan\Engine\Tenancy` | [DOC-082E](tenancy.md) | database, cache | چنداجاره‌ای |
| 53 | `compat` | `Tavaan\Engine\Compat` | [DOC-083E](compat.md) | — | Polyfill برای PHP 8.3/8.4 |

---

## ۳. گراف وابستگی (Dependency Graph)

```
                              ┌───────────┐
                              │ container │ (ریشه — بدون وابستگی)
                              └─────┬─────┘
             ┌──────────────────────┼──────────────────────┐
             ▼                      ▼                      ▼
        ┌─────────┐           ┌──────────┐           ┌──────────┐
        │ config  │           │ pipeline │           │  module  │
        └────┬────┘           └────┬─────┘           └────┬─────┘
             │                     │                      │
             ▼                     ▼                      │
        ┌─────────┐           ┌──────────┐                │
        │  event  │◀──────────│  error   │                │
        └────┬────┘           └────┬─────┘                │
             │                     │                      │
     ┌───────┼───────┬─────────────┼──────────┐           │
     ▼       ▼       ▼             ▼          ▼           ▼
 ┌──────┐┌──────┐┌───────┐    ┌────────┐ ┌─────────┐ ┌────────┐
 │ log  ││cache ││crypt  │    │  http  │ │database │ │  i18n  │
 └──┬───┘└──┬───┘└──┬────┘    └───┬────┘ └────┬────┘ └───┬────┘
    │       │       │             │           │          │
    │       │       │        ┌────┴────┐      │          │
    │       │       │        ▼         ▼      ▼          ▼
    │       │       │   ┌────────┐┌───────┐┌─────┐  ┌──────────┐
    │       │       └──▶│session ││router ││ orm │  │validation│
    │       │           └───┬────┘└───────┘└──┬──┘  └────┬─────┘
    │       │               │                 │          │
    │       │               ▼                 ▼          ▼
    │       │          ┌────────┐        ┌────────┐ ┌────────┐
    │       │          │  auth  │───────▶│  rbac  │ │  view  │
    │       │          └───┬────┘        └───┬────┘ └───┬────┘
    │       │              │                 │          │
    │       ▼              ▼                 ▼          ▼
    │  ┌─────────┐    ┌─────────┐       ┌────────┐ ┌────────┐
    │  │  queue  │    │  audit  │       │  api   │ │  mail  │
    │  └────┬────┘    └─────────┘       └────────┘ └───┬────┘
    │       │                                          │
    ▼       ▼                                          ▼
 ┌──────────────┐                              ┌──────────────┐
 │observability │                              │ notification │
 └──────────────┘                              └──────────────┘
```

### ۳٫۱ ترتیب ساخت اجباری (Build Order) 🔒

```
Phase 1 (M1) : container → config → event → pipeline → error → log → compat → optimizer
Phase 2 (M2) : http → router → session → cookie → view → i18n → jalali → cors
Phase 3 (M3) : database → schema → migration → orm → pagination → validation
Phase 4 (M4) : crypt → hash → auth → rbac → security → cache → audit
Phase 5 (M5) : queue → schedule → console → filesystem → media → mail → notification → http-client → backup
Phase 6 (M6) : api → observability → search → module → tenancy → feature-flag → realtime
Phase 7 (M7) : workflow → report → payment
Phase 8 (M8) : scaffold → admin → plugin → test → upgrade
```

> 🔒 هیچ موتوری قبل از وابستگی‌هایش ساخته نمی‌شود.
> ⚠️ برای هر فاز، تست Integration فاز قبلی باید سبز باشد.

---

## ۴. ماتریس افزونه‌های PHP (Extensions) و Fallback

| افزونه | استفاده‌کننده | اجباری؟ | Fallback | رفتار بدون افزونه |
|---|---|---|---|---|
| `pdo` | database | 🔒 بله | — | بوت شکست می‌خورد |
| `pdo_mysql` / `pdo_pgsql` / `pdo_sqlite` | database | حداقل یکی | — | خطا با پیام واضح |
| `mbstring` | Support\Str, i18n | ⚠️ توصیه | `iconv` + پیاده‌سازی خالص | کاهش سرعت، هشدار لاگ |
| `openssl` | crypt, hash, http-client | 🔒 بله | — | بوت شکست می‌خورد |
| `json` | همه‌جا | 🔒 بله (built-in در 8+) | — | — |
| `hash` | crypt, audit | 🔒 بله (built-in) | — | — |
| `random` | crypt, id | 🔒 بله (built-in 8.2) | — | — |
| `redis` | cache, session, queue | ❌ خیر | `file`/`database` | درایور Redis غیرفعال + هشدار |
| `apcu` | cache | ❌ خیر | `file` | درایور APCu غیرفعال |
| `memcached` | cache, session | ❌ خیر | `file` | غیرفعال |
| `intl` | i18n, jalali | ❌ خیر | پیاده‌سازی خالص `Jalali`/`PluralRules` | دقت یکسان، سرعت کمتر |
| `gd` | media | ❌ خیر | بدون پردازش (فقط ذخیره) | `EngineCapabilityException` در resize |
| `imagick` | media | ❌ خیر | `gd` | — |
| `bcmath` | Support\Math | ❌ خیر | `Decimal` خودنوشته | سرعت کمتر |
| `gmp` | Support\Math | ❌ خیر | `Decimal` خودنوشته | — |
| `zip` | backup, report(xlsx) | ❌ خیر | `ZipWriter` خودنوشته (Store/Deflate خام) | پشتیبانی کامل با کد خالص |
| `zlib` | http (compress), backup | ❌ خیر | بدون فشرده‌سازی | پاسخ فشرده نمی‌شود |
| `sockets` | realtime | ❌ خیر | SSE (بدون WebSocket) | فقط SSE |
| `pcntl` | queue, console | ❌ خیر | بدون Signal Handling | `Ctrl+C` → توقف فوری |
| `posix` | console, queue | ❌ خیر | `get_current_user()` | — |
| `swoole` | http (resident) | ❌ خیر | FPM/Built-in | حالت Resident غیرفعال |
| `opcache` | optimizer | ⚠️ توصیه | — | Preload غیرفعال |
| `curl` | http-client | ❌ خیر | Stream Wrapper (`fopen`) | بدون HTTP/2 |
| `fileinfo` | media, filesystem | ⚠️ توصیه | Magic Bytes خودنوشته | دقت کمتر در MIME |
| `exif` | media | ❌ خیر | بدون EXIF | — |
| `iconv` | i18n | ❌ خیر | `mb_convert_encoding` | — |
| `ctype` | validation | ❌ خیر | regex | — |
| `filter` | validation | ❌ خیر | regex خودنوشته | — |
| `dom`/`xmlwriter` | report, mail (HTML→Text) | ❌ خیر | Parser خودنوشته | — |
| `sodium` | crypt | 💡 اختیاری | openssl | Ed25519 با openssl |

### ۴٫۱ الگوی بررسی Capability

```php
final class Capability
{
    /** @param list<string> $anyOf حداقل یکی باید موجود باشد */
    public static function require(string $ext, string $feature, array $anyOf = []): void
    {
        if (\extension_loaded($ext)) {
            return;
        }
        foreach ($anyOf as $alt) {
            if (\extension_loaded($alt)) {
                return;
            }
        }
        throw new EngineCapabilityException(
            messageId: 'engine.capability_missing',
            context: ['extension' => $ext, 'feature' => $feature, 'alternatives' => $anyOf],
            hint: "افزونهٔ `{$ext}` را نصب کنید یا درایور جایگزین را در config انتخاب نمایید.",
            docsUrl: 'docs/03-engines/README.md#extensions',
        );
    }

    public static function has(string $ext): bool
    {
        return \extension_loaded($ext);
    }

    /** @return array<string, bool> گزارش کامل برای `tvn about` */
    public static function report(): array
    {
        $result = [];
        foreach (self::REQUIRED as $ext) { $result[$ext] = ['required' => true,  'loaded' => self::has($ext)]; }
        foreach (self::OPTIONAL  as $ext) { $result[$ext] = ['required' => false, 'loaded' => self::has($ext)]; }
        return $result;
    }

    private const array REQUIRED = ['pdo', 'openssl', 'json', 'hash', 'mbstring'];
    private const array OPTIONAL = ['redis','apcu','memcached','intl','gd','imagick','bcmath',
        'gmp','zip','zlib','sockets','pcntl','posix','swoole','opcache','curl','fileinfo','exif',
        'iconv','sodium','dom','xmlwriter'];
}
```

🔒 در `bin/tvn about` و `tvn doctor`، وضعیت همهٔ افزونه‌ها با Fallback فعال نمایش داده می‌شود.

---

## ۵. اولویت‌بندی و حجم تقریبی

| موتور | اولویت | LOC تقریبی | کلاس | تست | پیچیدگی |
|---|---|---|---|---|---|
| container | P0 | 1200 | 12 | 45 | ★★★★ |
| config | P0 | 600 | 6 | 25 | ★★ |
| event | P0 | 900 | 9 | 38 | ★★★ |
| pipeline | P0 | 250 | 3 | 12 | ★ |
| error | P0 | 800 | 10 | 30 | ★★★ |
| log | P0 | 900 | 11 | 28 | ★★★ |
| http | P0 | 2800 | 28 | 70 | ★★★★★ |
| router | P0 | 1400 | 14 | 55 | ★★★★ |
| session | P0 | 900 | 10 | 32 | ★★★ |
| view | P0 | 1800 | 16 | 48 | ★★★★★ |
| database | P0 | 3200 | 30 | 85 | ★★★★★ |
| schema | P0 | 1100 | 12 | 35 | ★★★★ |
| migration | P0 | 800 | 9 | 25 | ★★★ |
| orm | P0 | 4000 | 38 | 110 | ★★★★★ |
| validation | P0 | 2200 | 24 | 90 | ★★★★ |
| crypt | P0 | 600 | 6 | 30 | ★★★★ |
| hash | P0 | 350 | 5 | 20 | ★★ |
| auth | P0 | 2000 | 22 | 65 | ★★★★ |
| rbac | P0 | 1600 | 18 | 55 | ★★★★ |
| security | P0 | 1500 | 16 | 60 | ★★★★ |
| cache | P0 | 1400 | 14 | 45 | ★★★ |
| i18n | P0 | 900 | 10 | 35 | ★★★ |
| console | P1 | 1600 | 18 | 45 | ★★★ |
| filesystem | P1 | 1200 | 13 | 40 | ★★★ |
| queue | P1 | 2200 | 24 | 70 | ★★★★ |
| schedule | P1 | 700 | 8 | 28 | ★★★ |
| mail | P1 | 1400 | 15 | 40 | ★★★★ |
| notification | P1 | 900 | 11 | 30 | ★★★ |
| http-client | P1 | 1300 | 14 | 45 | ★★★★ |
| media | P1 | 1500 | 16 | 40 | ★★★★ |
| api | P1 | 1200 | 13 | 40 | ★★★ |
| observability | P1 | 1400 | 15 | 40 | ★★★ |
| audit | P1 | 700 | 8 | 25 | ★★★ |
| jalali | P0 | 800 | 8 | 60 (Vector) | ★★★★ |
| search | P1 | 1500 | 15 | 45 | ★★★★ |
| workflow | P2 | 2000 | 22 | 55 | ★★★★★ |
| report | P2 | 1600 | 18 | 45 | ★★★★ |
| payment | P2 | 1800 | 20 | 50 | ★★★★ |
| feature-flag | P2 | 600 | 7 | 25 | ★★ |
| realtime | P2 | 1200 | 13 | 30 | ★★★★ |
| tenancy | P1 | 1000 | 11 | 35 | ★★★★ |
| module | P0 | 900 | 10 | 30 | ★★★ |
| optimizer | P1 | 800 | 9 | 25 | ★★★ |
| scaffold | P1 | 1200 | 14 | 30 | ★★★ |
| admin | P2 | 2500 | 25 | 40 | ★★★★ |
| plugin | P1 | 900 | 10 | 28 | ★★★ |
| test | P0 | 2500 | 28 | (خودش تست است) | ★★★★★ |
| backup | P2 | 700 | 8 | 22 | ★★★ |
| upgrade | P2 | 600 | 7 | 18 | ★★★ |
| compat | P0 | 400 | 5 | 20 | ★★ |
| **جمع** | | **~۶۴٬۰۰۰** | **~۵۷۰** | **~۱٬۹۰۰** | |

---

## ۶. قالب مشترک مستند موتور

→ `docs/templates/engine-spec-template.md` (DOC-150)

---

## ۷. بلوک ماشین‌خوان (Machine-Readable Registry) 🧠

```yaml
# machine-readable: tavaan.engines.registry.v1
meta:
  framework: tavaan
  version: 1.0.0
  php_min: "8.2.0"
  dependency_policy: zero-external
  total_engines: 49

build_order:
  - phase: 1
    milestone: M1
    engines: [container, config, event, pipeline, error, log, compat, optimizer]
  - phase: 2
    milestone: M2
    engines: [http, router, session, cookie, view, i18n, jalali, cors]
  - phase: 3
    milestone: M3
    engines: [database, schema, migration, orm, pagination, validation]
  - phase: 4
    milestone: M4
    engines: [crypt, hash, auth, rbac, security, cache, audit]
  - phase: 5
    milestone: M5
    engines: [queue, schedule, console, filesystem, media, mail, notification, http-client, backup]
  - phase: 6
    milestone: M6
    engines: [api, observability, search, module, tenancy, feature-flag, realtime]
  - phase: 7
    milestone: M7
    engines: [workflow, report, payment]
  - phase: 8
    milestone: M8
    engines: [scaffold, admin, plugin, test, upgrade]

engines:
  - id: container
    namespace: Tavaan\Engine\Container
    path: engines/container
    priority: P0
    depends_on: []
    extensions: {required: [], optional: []}
    loc_estimate: 1200
    doc: docs/03-engines/container.md
    facade: null
    tables: []
  - id: config
    namespace: Tavaan\Engine\Config
    path: engines/config
    priority: P0
    depends_on: [container]
    extensions: {required: [], optional: []}
    loc_estimate: 600
    doc: docs/03-engines/config.md
    facade: {name: Config, accessor: config}
    tables: []
  - id: event
    namespace: Tavaan\Engine\Event
    path: engines/event
    priority: P0
    depends_on: [container, config]
    extensions: {required: [], optional: []}
    loc_estimate: 900
    doc: docs/03-engines/event.md
    facade: {name: Event, accessor: events}
    tables: [tvn_outbox, tvn_inbox]
  # … (همهٔ ۴۹ موتور با همین ساختار)

extensions:
  required: [pdo, openssl, json, hash, mbstring]
  optional:
    - {name: redis,     engines: [cache, session, queue], fallback: file}
    - {name: apcu,      engines: [cache],                 fallback: file}
    - {name: memcached, engines: [cache, session],        fallback: file}
    - {name: intl,      engines: [i18n, jalali],          fallback: native}
    - {name: gd,        engines: [media],                 fallback: none}
    - {name: imagick,   engines: [media],                 fallback: gd}
    - {name: bcmath,    engines: [support-math],          fallback: Decimal}
    - {name: zip,       engines: [backup, report],        fallback: ZipWriter}
    - {name: zlib,      engines: [http, backup],          fallback: none}
    - {name: sockets,   engines: [realtime],              fallback: sse}
    - {name: pcntl,     engines: [queue, console],        fallback: none}
    - {name: swoole,    engines: [http],                  fallback: fpm}
    - {name: opcache,   engines: [optimizer],             fallback: none}
    - {name: curl,      engines: [http-client],           fallback: stream}
    - {name: fileinfo,  engines: [media, filesystem],     fallback: magic-bytes}
    - {name: sodium,    engines: [crypt],                 fallback: openssl}
    - {name: dom,       engines: [report, mail],          fallback: native-parser}
```

---

## ۸. چک‌لیست آماده‌بودن موتور (Engine Readiness)

قبل از Merge هر موتور:

- [ ] `module.json` معتبر (`tvn engine:validate <name>`)
- [ ] Provider با `register()`/`boot()` و بدون side effect در register
- [ ] همهٔ Contractها در `core/Contracts/` و `@api` خورده
- [ ] `config/<name>.php` با پیش‌فرض کامل و کامنت
- [ ] `lang/fa` و `lang/en` با کلیدهای یکسان (`tvn lang:check`)
- [ ] همهٔ MessageIdها در کد، کلید ترجمه دارند (نه رشتهٔ خام)
- [ ] تست Unit ≥ ۹۰٪ پوشش کلاس‌های عمومی
- [ ] تست Feature برای جریان اصلی
- [ ] Benchmark با اهداف عددی (`benchmarks/<name>.bench.php`)
- [ ] مستند `docs/03-engines/<name>.md` کامل (۱۶ بخش)
- [ ] بلوک machine-readable در انتهای مستند
- [ ] `README.md` در پوشهٔ موتور با شروع سریع
- [ ] `examples/basic.php` اجراشدنی
- [ ] هیچ `vendor/` یا `require` خارجی
- [ ] PHPStan level max سبز
- [ ] PHP-CS-Fixer بدون diff
- [ ] صفر `E_DEPRECATED` در PHP 8.2/8.3/8.4
- [ ] ثبت در رجیستری (همین فایل) و به‌روزرسانی گراف وابستگی
