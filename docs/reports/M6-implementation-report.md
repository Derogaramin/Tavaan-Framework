# گزارش پیاده‌سازی M6 — فریمورک تَوان (سرویس‌ها)

**تاریخ:** ۲۰۲۶-۱۰-۱۶ • **نگارش:** `0.6.0` • **مخزن:** `Derogaramin/Tavaan-Framework`
**اسناد بالادستی:** DOC-120 (`docs/08-roadmap/milestones.md`) و DOC-121 (`docs/08-roadmap/agent-backlog.md`)
**پیش‌نیازها:** M2 (وب)، M3 (داده)، M4 (امنیت) + M5 (زیرساخت) — همه سبز در نقطهٔ شروع (۷۰۴ تست)

---

## ۱. خلاصهٔ اجرایی

هر **۶ تیکت M6** به‌طور کامل پیاده‌سازی، تست و مستند شد:

| تیکت | موتور | سند | اولویت | تست‌ها | وضعیت |
|---|---|---|---|---|---|
| TVN-0067 | **Observability** | DOC-067 | P1 | ۳۸ | ✅ |
| TVN-0052 | **Search** | DOC-052 | P1 | ۳۳ | ✅ |
| TVN-0044 | **API** | DOC-044 | P1 | ۴۰ | ✅ |
| TVN-0082 | **Tenancy** | DOC-082E + DOC-028 | P1 | ۳۵ | ✅ |
| TVN-0075 | **FeatureFlag** | DOC-075 | P2 | ۲۲ | ✅ |
| TVN-0045 | **Realtime** | DOC-045 | P2 | ۲۹ | ✅ |

**حجم تحویل:** ~۱۵٬۶۰۰ خط کد منبع در `engines/*/src/` (۱۵۰ فایل PHP جدید در موتورها+قراردادها) + ~۴٬۸۰۰ خط تست + ۳۷۶ خط قرارداد در `core/Contracts/{Observability,Search,Tenancy,Realtime}` + ۶ `module.json` + ۳ migration + ۱۲ فایل زبان (fa/en) + ۷ Facade جدید + ۷ دستور CLI. **صفر وابستگی بیرونی** (مطابق DOC-001) — Thrift Compact، YAML emitter، ایندکس معکوس BM25، FTS5 driver، RFC 6455 frames و SSE همگی خودنوشته‌اند.

**نتیجهٔ نهایی:** `۹۰۱ تست / ۶۳۷۱ assertion سبز` (دو اجرای متوالی پایدار) • lint کامل پاک • `۴۵/۴۵ موتور فعال` • نسخه `0.5.1 → 0.6.0`.

---

## ۲. ترتیب اجرا (الگوریتم DOC-121 §7)

همهٔ ۶ تیکت M6 هم‌فاز بودند؛ اول **P1ها** (observability → search → api → tenancy) سپس **P2ها** (feature-flag → realtime). دو رعایت ترتیبی اضافی: tenancy پیش از feature-flag (قاعدهٔ `tenant` به `TenantContext` تکیه دارد) و realtime آخر (به queue/event هر دو وابسته است).

---

## ۳. موتور Observability (TVN-0067)

**مسیر:** `engines/observability/` — Facadeها: `Metrics`، `Trace` — bindingها: `metrics`، `tracer`، `health`، `debugbar`، `stopwatch`

### ۳٫۱ Metrics (Prometheus)
- چهار نوع سنجه: **Counter** (فقط‌افزایشی — افزایش منفی → `observability.negative_increment`)، **Gauge**، **Histogram** (ذخیرهٔ **غیرتجمعی** سطل‌ها و تجمیع در formatter — باگ رایج double-counting avoided)، **Summary** (quantile با **Algorithm R** reservoir — حافظهٔ کران‌دار ۱۰۲۴ نمونه).
- `timer(name, fn)` با `hrtime` — حتی در exception (finally) ثبت می‌شود.
- **Prometheus text format** کامل: `# HELP`/`# TYPE`، bucketهای تجمعی `le` تا `+Inf`، `_sum`/`_count`، quantileها، escape برچسب (`\`، `"`، newline) و sanitize نام (`[^a-zA-Z0-9_:] → _`) بدون تکرار prefix.
- **پایستگی بین درخواست‌ها** (opt-in با `TVN_METRICS_PERSIST`): write-through روی cache با کلید `tvn:metrics:snapshot`؛ `collect()` سنجه‌های ذخیره‌شده‌ای که در درخواست جاری لمس نشده‌اند را rehydrate می‌کند — endpoint ‏`/metrics` تجمعی واقعی نشان می‌دهد (در smoke test تأیید شد: شمارنده‌ها بین restartهای سرور باقی ماندند).

### ۳٫۲ Tracing (خودنوشته، بدون SDK)
- `Tracer::startSpan/withSpan/currentSpan` با **پشتهٔ والد-فرزندی** و `end()` idempotent (double-export ندارد).
- **Sampling قطعی** (`RateSampler` بر اساس ۸ هگز اول trace-id) — همهٔ spanهای یک trace هم‌سرنوشت‌اند؛ فرزندان از والد به ارث می‌برند (parent-based)؛ `NonRecordingSpan` بدون سربار.
- **W3C TraceContext**: پارس/تزریق `traceparent` با رد idهای تمام‌صفر (طبق spec)؛ یکپارچه با `TraceContextMiddleware` موجود در هسته.
- **۴ Exporter**: `ArrayExporter` (تست)، `LogExporter` (با 🔒 mask کلیدهای password/token/secret/…)، `OtlpHttpExporter` (OTLP/HTTP JSON — ساختار `resourceSpans→scopeSpans→spans`، AnyValue تایپ‌شده، timeUnixNano، transport تزریقی)، `JaegerUdpExporter` با **Thrift Compact Protocol writer کاملاً خودنوشته** (zigzag varint با شیفت منطقی ۶۴ بیتی، field delta، struct stack، Batch/Process/Span/Tag/Log) — **round-trip در تست** با reader کمکی تأیید شد.
- flush خودکار در `http.request.terminated`.

### ۳٫۳ Health و Debug Bar
- `HealthManager` با نرمال‌سازی ۶ شکل خروجی (bool/Up/Down/آرایه/CheckResult/Throwable→down+پیام)، تفکیک **live/ready** (kind per-check)، responseTime، `GET /health/live|ready` با ۲۰۰/۵۰۳ و بررسی‌های پیش‌فرض database/cache/queue/disk (فقط وقتی binding موجود باشد).
- **Debug Bar** با 🔒 **guard سه‌گانه** (`app.debug && env≠production && config`)؛ خروجی کاملاً `htmlspecialchars` (تست XSS: `<script>` هرگز خام بیرون نمی‌آید)؛ تزریق فقط در HTML 200 پیش از `</body>`؛ داده از Stopwatch، QueryLog، رویدادها (hook جدید `dispatchLog` در EventDispatcher — **بدون سربار وقتی recording خاموش است**)، route، middleware (getter جدید `HttpKernel::resolvedMiddleware()`) و config masked.

### ۳٫۴ MetricsMiddleware
ثبت خودکار `http_requests_total{route,method,status}` و `http_request_duration_seconds` برای هر درخواست. 🔒 **cardinality control**: برچسب route = نام route یا الگوی URI (هرگز مسیر واقعی با id). هرگز درخواست را نمی‌اندازد (try/cetch کامل).

---

## ۴. موتور Search (TVN-0052)

**مسیر:** `engines/search/` — Facade: `Search` — درایورها: `native|sqlite|mysql|pgsql|meilisearch|typesense`

### ۴٫۱ Native Inverted Index (فارسی‌آگاه — شاهکار موتور)
- **نرمال‌سازی حیاتی فارسی** (`Persian::normalize`): ی→ي و ک→ك (طبق DOC-052 §4)، ة/ۀ→ه، آ/أ/إ→ا، حذف اعراب (U+064B–065F, U+0670) و تحریر، **حذف نیم‌فاصله** (کتاب‌ها ≡ کتابها)، ارقام فارسی/عربی→لاتین.
- **Stemming** با ۱۸ پسوند رایج در **دو فرم فارسی و عربی** (چون متن پیش از stem نرمال می‌شود — این جزئیتهای در حین پیاده‌سازی با تست کشف و رفع شد) + محافظ طول ریشه (`len > suffix+2`) تا «نان»/«گل» over-stem نشوند.
- **BM25** طبق فرمول سند (idf = log((N+1)/(df+0.5))، k1=1.2) با **boost فیلد** (`title^3`)؛ رتبه‌بندی واژهٔ نادر در تست تأیید شد (AC-SEARCH-08).
- **Facet** فقط روی فیلدهای `filterable` (🔒 ضد نشت داده)، **Sort** فقط `sortable`، فیلترها با عملگرهای eq/ne/lt/lte/gt/gte/in/nin.
- **Highlight با escape-then-mark** (🔒 AC-SEARCH-05): ابتدا کل متن `htmlspecialchars`، سپس فقط تگ‌های sanitize‌شدهٔ `<mark>` — حتی تگ‌های سفارشی نامعتبر به جفت پیش‌فرض برمی‌گردند؛ تطبیق در سطح واژه با normalize+stem (نیم‌فاصله/اعراب در متن اصلی حفظ می‌شود).
- **Suggest**: prefix + substring + mb-Levenshtein≤1 (خودنوشته UTF-8) با display-map (فرم خام واژه)؛ «آیا منظور شما…» هنگام نتیجهٔ خالی — در تست «سامسونق»→«سامسونگ» تأیید شد.
- **پایستگی اتمیک JSON** (tmp+rename) در `storage/search/`؛ 🔒 محافظ DoS: طول کوئری (۵۰۰)، تعداد توکن (۶۴)، طول Levenshtein (۲۴)؛ 🔒 نام ایندکس regex-validated (ضد path traversal).

### ۴٫۲ درایورهای SQL و سرویس‌ها
- **SQLite FTS5** (آزموده در sandbox): متن نرمال‌شده در ستون‌های searchable + `payload` JSON در UNINDEXED، رتبه‌بندی `bm25()`، فیلتر عددی با CAST، facet با GROUP BY، suggest با **prefix query بدون quote** (نکتهٔ ظریف FTS5 که حین کار اصلاح شد)، کاوش قابلیت FTS5 در ساخت (skip graceful در تست).
- **MySQL FULLTEXT**: MATCH…AGAINST (NATURAL LANGUAGE)، `WITH PARSER ngram` اختیاری، upsert با ON DUPLICATE KEY، شناسه‌ها backtick-validated.
- **PostgreSQL**: `to_tsvector('simple', …)` + GIN + `ts_rank` + tsquery با OR کردن توکن‌های quote‌شده؛ bindingهای INSERT…SELECT با ترتیب دقیق ستون/عبارت.
- **Meilisearch/Typesense**: آداپتر HTTP با **transport تزریقی** (تست بدون سرور واقعی) + fallback بر stream context؛ نگاشت کامل hits/facets/highlight به `SearchResult` مشترک.
- 🔒 همهٔ درایورهای SQL: prepared statements، whitelist ستون‌ها از config، رد شناسهٔ نامعتبر.

### ۴٫۳ ایندکس خودکار
trait `Searchable` (searchIndex/searchId/toSearchableArray/searchable()/unsearch()) + listenerهای `model.saved`/`model.deleted` در provider — فقط مدل‌های Searchable، کاملاً best-effort (شکست ایندکس هرگز ذخیرهٔ مدل را نمی‌شکند). `SearchManager` پیکربندی را **زنده** می‌خواند (تغییرات config پس از boot مؤثرند) و `search:reindex` با cursor/chunk.

---

## ۵. موتور API (TVN-0044)

**مسیر:** `engines/api/` — بدون Facade (طبق سند — JsonResource پایهٔ کلاس است)

### ۵٫۱ JsonResource
- همهٔ متدهای شرطی سند: `when` (با **closure lazy** — در تست تأیید شد شرط false هرگز closure را اجرا نمی‌کند)، `whenLoaded`، `whenHas` (خالی→حذف)، `whenCounted`، `mergeWhen`، `whenPivotLoaded`.
- `MissingValue`/`MergeValue` هر دو **IteratorAggregate** — هم سبک `'key' => mergeWhen(…)` و هم سبک spread `...mergeWhen(…)` کار می‌کند.
- resolve بازگشتی: JsonResource تودرتو، Arrayable، JsonSerializable، Paginator، Closure — و حذف کامل MissingValue از هر عمق.
- **بدون تغییر موتور http**: بازگشت Resource از controller از مسیر موجود `JsonSerializable` در `ControllerResolver::normalizeResult` عبور می‌کند؛ درخواست جاری از container (با fallback امن و try/catch — singleton `'request'=>null` بوست‌شدهٔ HttpServiceProvider مدیریت می‌شود).
- `AnonymousResourceCollection` برای Paginator: `{"data": […], "links": {first/last/prev/next}, "meta": {…}}` (AC-API-02).

### ۵٫۲ Versioning / Idempotency / Throttle
- `api.version` با سه استراتژی + پارامتر صریح middleware (اولویت بالا)، 🔒 نگارش پشتیبانی‌نشده → **RFC 9457** 404، هدرهای `Deprecation`/`Sunset`/`X-Api-Version`.
- `idempotent`: کلید `Idempotency-Key`+مسیر (hash‌شده 🔒)، replay کامل (status/headers/body) با `X-Idempotent-Replay: true`، 🔒 **قفل in-progress** (درخواست موازی با همان کلید → 409 — در تست با دو فراخوانی متوالی و cache از قبل قفل‌شده تأیید شد)، فقط status<500 کش می‌شود (خطای سرور replay نمی‌شود — smoke: دو POST یکسان → بدنه یکسان، فراخوانی handler فقط یک بار).
- `throttle`: نام‌دار (`throttle:api` از `config('api.rate_limit')`) و عددی (`throttle:60,1`)، پنجرهٔ ثابت با `add/increment` اتمیک، هویت **user → X-Api-Key → IP** (🔒 TEN-SEC-10-friendly)، هدرهای X-RateLimit-* و Retry-After، **Clock-aware** (تست عبور پنجره با `Clock::setTestNow`).
- aliasهای middleware (`throttle`/`idempotent`/`api.version`) در register به `http.middleware.aliases` merge می‌شوند (HttpKernel تنبل است — aliasها قبل از اولین درخواست ثبت‌اند).

### ۵٫۳ OpenAPI 3.1 Generator
- Attributeهای `#[Api]`/`#[ApiResponse]` (repeatable)/`#[ApiParam]` با reflection روی actionهای `Class@method`/array/Closure/invokable.
- **requestBody خودکار از FormRequest::rules()** (newInstanceWithoutConstructor): required، type از integer/numeric/boolean/array/date/email/url/uuid/file، maxLength/minLength/maximum/minimum/enum/nullable.
- پارامتر مسیر با نوع از wheres (`whereNumber`→integer، `whereUuid`→format uuid)، security از middleware auth → `bearerAuth`، فیلتر نگارش از uri/name.
- **YAML emitter خودنوشته**: کلید/مقدار امن plain، quote هوشمند (رزروها/عددنماها/کاراکتر خاص/کنترل)، `{}`/`[]` برای خالی‌ها، لیستِ map با چینش `- key:`، escape کامل — خروجی smoke برای `routes/api.php` نمونه تولید و بررسی چشمی شد.
- `api:docs --format=yaml|json --output=` با 🔒 محافظ مسیر (خروجی باید داخل base path بماند — تست `../evil.yaml` → exit 1).

---

## ۶. موتور Tenancy (TVN-0082 — پیاده‌سازی DOC-028)

**مسیر:** `engines/tenancy/` — Facade: `Tenant` — جدول: `tvn_tenants`

### ۶٫۱ سه استراتژی
- **A (Shared/Row-Level)**: `TenantScope` (Global Scope — 🔒 بدون context روی مدل tenant-aware → `TenantNotResolvedException`، هیچ کوئری بی‌scope اجرا نمی‌شود — AC-TEN-01)، `TenantObserver` (پرکردن خودکار tenant_id؛ 🔒 insert بدون context **fail** می‌شود نه اجرا با tenant قبلی — TEN-SEC-06)، trait `BelongsToTenant` (🔒 override ‏`isFillable` → tenant_id هرگز mass-assign نمی‌شود؛ تلاش کاربر → `MassAssignmentException` صریح — TEN-SEC-02)، `allowGlobalTenant` برای مدل‌های سراسری.
- **B (DB-per-Tenant)**: `DatabaseTenantManager` — اتصال پویا `tenant` از قالب مرکزی + settingهای tenant (db_driver/host/username/**password رمزگشایی‌شده با encrypter**)، provisioning برای sqlite (فایل)/MySQL (CREATE DATABASE)/PG (با بررسی pg_database) با 🔒 محافظ شناسه (دو سطح: SQL identifier سخت‌گیرانه، مسیر sqlite بدون `..`)، جابه‌جایی `setDefaultConnection` با **بازگردانی قطعی در finally**.
- **C (Schema-per-Tenant)**: `SET search_path TO "tenant_x", public` مخصوص PG با sanitize identifier.

### ۶٫۲ دو باگ ریشه‌ای که حین تست‌نویسی کشف و رفع شد
1. **memo کهنه**: `create()` پیش از insert، `findByIdentifier` را (برای بررسی duplicate) صدا می‌زد و **null را memo می‌کرد** — پس از insert، lookupهای بعدی همان tenant را «نیافت» گزارش می‌کردند (و بررسی duplicate با UNIQUE violation می‌ترکید). رفع: `flushMemo()` پس از insert.
2. **مرکزِ گم‌شده**: `ConnectionManager::setDefaultConnection` مقدار `config('database.default')` را **بازنویسی** می‌کند؛ پس از اتصال tenant، «اتصال مرکزی» خودِ `tenant` خوانده می‌شد و disconnect به جایی برنمی‌گشت + کوئری‌های ثبت tenant به DB tenant می‌رفتند. رفع: **ثبت نام اتصال مرکزی در زمان ساخت** (`centralCaptured`) در هر دو کلاس `TenantManager` و `DatabaseTenantManager` — ثبت مرکزی اکنون هرگز از defaultِ جابه‌جاشده نمی‌خواند.

### ۶٫۳ Resolverها، Quota و جداسازی
- زنجیرهٔ resolver دقیقاً به ترتیب DOC-028 (Domain→Header→Path→Token→Query→Default) — هر ورودی regex-sanitize (🔒 log-injection/overflow)، QueryResolver فقط-dev، TokenResolver claim از JWT با 🔒 رد `exp` گذشته و decode-unverified **opt-in**.
- `IdentifyTenantMiddleware` با `terminate` → 🔒 پاک‌سازی context (TEN-SEC-05 — تست ۱۰۰۰ run متوالی بدون نشت).
- `Tenant::withoutCurrentScope` با **مجوز gate (`tenancy.cross_tenant`) + رویداد Audit** (در HTTP بدون مجوز → رد؛ CLI مجاز).
- **QuotaGuard** پنجره‌دار (minute/hour/day/month/total با کلید `tvn:quota:{id}:{resource}:{window}`، TTL متناسب، Clock-aware) — سرریز → رویداد `TenantQuotaExceeded` + `TenantQuotaExceededException` (HTTP 429).
- **جداسازی Cache**: `TenantScopeStore` (دکوراتور Store در سطح کلید: `tvn:t{id}:`) به‌عنوان driver «tenant» در CacheManager ثبت می‌شود → `Cache::store('tenant')`؛ بدون context → bucket سراسری `tvn:tg:` (هرگز قاطی نمی‌شود) — TEN-SEC-03/AC-TEN-04 در تست تأیید شد.
- `TenantConfigRepository` (overlay settingها با پشتیبانی کلید literal **و** dot — باگ اولویت کلید نقطه‌دار حین تست رفع شد)، `TenantProvisioner` (create/migrateTenant/migrateAll با rate-limit و جمع‌آوری خطا/delete=archive→force)، trait صف `RestoresTenantContext` (capture id در dispatch؛ 🔒 job بدون tenant → fail).
- CLI: `tenant:create/list/migrate/delete (تأیید دوگانهٔ --force+--yes)/stats` و `db:tenant-guard` (اسکن Query Log — heuristic گزارش کوئری بدون tenant_id؛ کنترل امنیتی زمان‌اجرا بر عهدهٔ TenantScope است).

### ۶٫۴ تست نشت (AC-TEN-03)
**fuzzing صلبی**: ۴۰ سفارش interleaved بین دو tenant + ۱۰۰ راند خواندن تصادفی — در هر راند: شمارش دقیق ۲۰، tenant_id همهٔ ردیف‌ها متعلق به context جاری، و probe عنوانِ tenant مقابل → null. **صفر نشت.**

---

## ۷. موتور FeatureFlag (TVN-0075)

**مسیر:** `engines/feature-flag/` — Facade: `Feature` — جدول: `tvn_features`

- زنجیرهٔ resolve: **runtime define → database (با cache نسخه‌دار) → config**؛ Kill Switch روی همه اولویت مطلق دارد (حتی `value()` را default می‌کند).
- قواعد: `role` (hasAnyRole/hasRole)، `permission` (canAny/can)، `user_id`، `tenant` (وابستگی **نرم** به tenancy با class_exists)، `percentage`، `env`، `ip` — همه با method_exists-guard (بدون وابستگی سخت به rbac).
- 🔒 **percentage پایدار**: `crc32(seed|feature) % 100` — seed = user id → request IP → `anon`. **بهبود نسبت به فرمول سند**: نام feature هم در hash می‌آید تا bucketهای دو flag مستقل بمانند (در تست تأیید شد: هم‌پوشانی دو flag ۵۰٪ ≈ مستقل است نه ≈۱۰۰٪) — پایداری per-user/per-flag که هدف سند است حفظ شده (AC-FF-03).
- **A/B experiment** پایدار با پخش یکنواخت (تست: ۴۰۰ کاربر بین A/B متوازن، همان کاربر همیشه همان variant).
- درایور database: persist دو‌طرفه در `define`، cache با **کلید نسخه‌دار** (`feature:def:{version}:{name}` — 🔒 ضد poisoning و مصونیت از کش کهنه؛ تست: تغییر مستقیم DB + bump نسخه → مقدار تازه) و `flushCache`.
- `Feature::for($user)` → `ScopedFeature` (enabled/value/experiment در حوزهٔ کاربر).

---

## ۸. موتور Realtime (TVN-0045)

**مسیر:** `engines/realtime/` — Facadeها: `Broadcast`، `Realtime` — جدول: `tvn_broadcast_events`

### ۸٫۱ SSE (بدون هیچ ext — AC-RT-09)
- `SseStreamer` روی `StreamedResponse` موجود: هدرهای دقیق (`text/event-stream`، `no-store`، 🔒 `X-Accel-Buffering: no` برای nginx)، `retry:` (reconnect)، قالب `id:/event:/data:` با شکست data چندخطی، heartbeat کامنتی `: keep-alive`، تشخیص `connection_aborted`، سقف ایمن ۲۴ ساعته، و **حالت bounded** (max_seconds) برای تست/CLI.
- 🔒 نام رویداد sanitize (CR/LF/`:` → `.`) — تزریق خط به پروتکل ممکن نیست (تست اختصاصی).
- route ‏`GET /events/stream?channels=a,b` — 🔒 کانال private/presence بدون احراز → 403 (fail-closed)، بدون channels → 400.

### ۸٫۲ WebSocket (RFC 6455 خودنوشته)
- **`WebSocketFrame`**: encode/decode **incremental** (بافر ناقص → null، بدون مصرف)، سه فرم طول (7bit/16bit/64bit)، 🔒 mask اجباری از client (فریم unmasked → خطای پروتکل 1002)، opcodeهای text/binary/close/ping/pong/**continuation** (fragmentation کامل)، 🔒 سقف payload 16MB، close status/reason، و `acceptKey` که روی **بردار رسمی RFC 6455 §1.3** سبز است (`dGhlIHNhbXBsZSBub25jZQ==` → `s3pPLMBiTxaQ9kYGzzhZRbK+xOo=`).
- **`WebSocketServer`** با streamهای خالص PHP (بدون ext-sockets هم کار می‌کند): handshake با 🔒 **بررسی Origin** (لیست فعال → Origin غایب هم رد می‌شود)، max_clients → 503، پروتکل JSON کلاینت (`subscribe/unsubscribe/ping`) با 🔒 **توکن HMAC برای کانال خصوصی** (توکنِ کانالِ دیگر پذیرفته نمی‌شود)، کانال‌های عمومی از query string، fan-out با **cursor مستقل هر client** (هر اتصال هر رویداد را دقیقاً یک بار می‌گیرد)، ping→pong، close handshake، و **تست‌پذیری کامل با `stream_socket_pair`** (بدون TCP واقعی — subscribe+token+fan-out+ping+close همه در یک تست end-to-end).
- `realtime:websocket` با سیگنال‌های pcntl و shutdown تمیز.

### ۸٫۳ Brokerها و Broadcast
- **Array** (cap)، **Database** (polling با مکان‌نمای id + prune با TTL؛ 🔒 قالب cutoff دقیقاً هم‌قالب `Clock::isoNow` — باگ مقایسهٔ رشته‌ای ISO با قالب دیگر حین smoke کشف و رفع شد)، **Redis** (بازاستفاده از `RedisClientInterface` موتور queue: seq سراسری INCR + RPUSH/LTRIM/LRANGE — مدل LIST به‌جای Pub/Sub چون با poll سازگار است و اتصال blocking نمی‌خواهد؛ **fallback خودکار به database** اگر ping شکست).
- **قلاب `ShouldBroadcast` در EventDispatcher** (تغییر cross-engine، هم‌تراز با الگوی ShouldQueue): پس از listenerها، best-effort با لاگ شکست — هرگز dispatch را نمی‌شکند. `broadcastWhen()` محترم است.
- **احراز کانال**: `ChannelRegistry` با `{param}`/`*`/normalize (`private.`→`private:`)؛ `POST /broadcasting/auth` — ناشناس 403، callback رد 403، ثبت‌نشدهٔ خصوصی **403 (fail-closed)**؛ توکن `sign/verify` = HMAC-SHA256(app.key) + base64 payload + exp (تست tamper/forgery → null).
- **Presence**: join/heartbeat/leave/members روی cache با TTL و هرس خودکار هنگام خواندن (تست انقضا با Clock).

---

## ۹. تغییرات cross-engine (حداقلی و هدفمند)

| فایل | تغییر | دلیل |
|---|---|---|
| `engines/event/src/EventDispatcher.php` | hook ‏`broadcastIfNeeded` (ShouldBroadcast) + `startRecording/stopRecording/dispatchLog` | DOC-045 §3 / DOC-067 §5 — هر دو opt-in و بدون سربار وقتی خاموش |
| `engines/http/src/HttpKernel.php` | getter ‏`resolvedMiddleware()` | دادهٔ Debug Bar (DOC-067 §5 صریحاً می‌خواهد) |
| `engines/session/config/session.php` | `csrf.except = ['api/*', 'broadcasting/auth']` | لایهٔ API با token/bearer محافظت می‌شود؛ POSTهای API بدون آن با 419 می‌مردند (قرارداد رایج api-group) |
| `core/bootstrap.php` + `tavaan.json` | نسخه `0.6.0` | — |
| `config/providers.php` | +۶ provider | — |
| `.env.example` | بخش متغیرهای M6 | DX |

الگوی تکرارشوندهٔ M5 حفظ شد: قراردادها در `core/Contracts`، Facadeها در `core/Facade/Facades`، resolverهای ایستای سبک (`SearchResolver`/`ApiResolver`/`FeatureFlagResolver`/`TenancyResolver`) برای دسترسی traitها بدون coupling.

---

## ۱۰. راستی‌آزمایی — تست و Smoke

### ۱۰٫۱ تست‌ها
```
php bin/tvn test
→ Tests: 901 passed, 0 failed — Assertions: 6371 — Time: ~10s — Memory: 242 MB
→ دو اجرای متوالی: پایدار (بدون flake)
lint: find core engines tests -name '*.php' | xargs php -l → پاک
```
نکات پوشش برجسته: بردار رسمی RFC 6455 (accept-key)، round-trip کامل Thrift/Jaeger با reader کمکی، fuzzing نشت tenant (۱۰۰ راند)، پایداری percentage/experiment با توزیع آماری، FTS5 واقعی روی sqlite (با guard)، transportهای تزریقی OTLP/Meili/Typesense، جریان کامل WebSocket با socket-pair، Idempotency replay/409/500-no-cache، Clock-aware بودن throttle/quota/prune.

### ۱۰٫۲ Smoke زنده (php -S + درخواست HTTP)
| سناریو | نتیجه |
|---|---|
| `GET /api/v1/items` | ✅ 200 `{"data":[{…},{…}]}` + `X-RateLimit-Limit: 60, Remaining: 59` |
| `GET /api/v1/items/5` | ✅ 200 Resource wrapped |
| دو `POST /api/v1/items` با یک `Idempotency-Key` | ✅ هر دو 201، بدنه **یکسان**، دومی `X-Idempotent-Replay: true` (handler یک بار اجرا شد) |
| `GET /metrics` | ✅ Prometheus تجمعی **بین درخواست‌ها و restartها** (persist روی file cache) |
| `GET /health/live` / `ready` | ✅ 200 با checks (database/cache/queue/disk همه up) |
| `GET /events/stream?channels=demo&max_seconds=1` | ✅ 200 `text/event-stream` با `retry: 3000` |
| `POST /broadcasting/auth` (ناشناس) | ✅ 403 `{"error":"unauthenticated"}` |
| `tvn api:docs --format=yaml` | ✅ سند معتبر با paths از routes/api.php نمونه |
| `tvn tenant:create acme --quota=…` + `tenant:list` + `tenant:stats` | ✅ رکورد+quota نمایش درست |
| `tvn migrate` / `migrate:status` | ✅ ۳ migration جدید (tvn_tenants/tvn_features/tvn_broadcast_events) |
| `tvn queue:work --once` / `schedule:list` / `tvn about` | ✅ سالم — `45/45 موتور` |

---

## ۱۱. محدودیت‌های شناخته‌شده (صداقت مهندسی)

1. **درایورهای MySQL/PgSQL/Meili/Typesense جست‌وجو** بدون سرور واقعی آزموده نشدند (sandbox فقط sqlite دارد) — کد با prepared-statement و شناسهٔ whitelist نوشته شده و ساختارش با تست adapterهای HTTP (transport جعلی) پوشش یافت؛ FTS5 اما **کاملاً آزموده شد**.
2. **JaegerUdpExporter** با round-trip باینری آزموده شد نه با Jaeger واقعی؛ OTLP كذلك (ساختار payload مطابق spec ساخته و assert شد).
3. **WebSocket سرور** مقیاس‌پذیری Swoole را ندارد (stream_select تک‌فرایند، fan-out با polling) — برای استقرار بزرگ، سند Swoole resident را پیشنهاد می‌دهد (خارج از scope M6).
4. **Throttle پنجرهٔ ثابت** است (نه sliding window) — مرز پنجره می‌تواند burst دو‌برابری لحظه‌ای بدهد (رفتار استاندارد limiter ساده).
5. **OpenAPI generator** schema خروجی Resourceها را عمیق introspect نمی‌کند (x-tavaan-resource ثبت می‌شود) — requestBody از FormRequest کامل است.
6. **Metrics persist** read-modify-write است — در بار همزمانِ خیلی بالا تقریبی (برای دقت کامل: collector خارجی/pushgateway، مستند شد).
7. **db:tenant-guard** heuristic متنی روی Query Log است (ابزار گزارش/CI) — تضمین زمان‌اجرا با TenantScope است.
8. **Debug Bar** در `.env` توسعه با `TVN_DEBUG_BAR=true` فعال می‌شود؛ guard سه‌گانه اجازهٔ فعال‌شدن در production را نمی‌دهد حتی با debug=true.
9. **Tenancy استراتژی B**: `SchemaBuilder` اتصال پیش‌فرض را در نخستین resolve کش می‌کند — provisioning از raw statementهای connection استفاده می‌کند (مستند در تست)؛ اجرای `tvn tenant:migrate` روی استراتژی B در sandbox با sqlite-file آزموده شد ولی سناریوی MySQL واقعی نیازمند محیط عملیاتی است.

---

## ۱۲. گام بعدی — M7 (دامنه)

طبق DOC-121 §7 تیکت‌های آماده: **TVN-0071 Workflow (BPM)**، **TVN-0072 Report (xlsx خودنوشته)**، **TVN-0073 Payment (درگاه‌های ایرانی)** — پیش‌نیازها (M3/M4/M5/M6) اکنون کامل‌اند. پیشنهاد ترتیب: Report (کم‌وابستگی) → Payment (به http-client/audit M4-M5 تکیه دارد) → Workflow (سنگین‌ترین؛ از event/queue/tenancy M5-M6 استفاده می‌کند).
