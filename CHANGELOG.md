# Changelog — تَوان | Tavaan

فرمت: Keep a Changelog — نسخه‌گذاری: SemVer.

## [0.2.0] - 2026-10-15 — فاز M2: وب

### افزوده شد
- **TVN-0020 HTTP**: `Request`/`Response` کاملاً immutable با Bagها، `Request::capture/create`، پارس JSON امن (سقف حجم/عمق)، تشخیص Request Smuggling (CL+TE→400)، محافظ Header Injection، `HttpKernel` (scope per-request + terminate با flushScoped)، `ResponsePreparer` (ETag/xxh128، If-None-Match→304، gzip/deflate، Content-Length)، `ResponseFactory` (json/view/file/download/stream/streamDownload/problem RFC 9457)، `Redirector` (با محافظ Open Redirect)، `StreamedResponse`، `UploadedFile` (mime با magic bytes + move امن)، `RequestStack`
- **Middleware** (۱۱ عدد): TrustProxies (CIDR v4/v6 خالص، پیش‌فرض بسته)، MaintenanceMode (503+Retry-After)، SecurityHeaders (CSP/HSTS/nosniff/… + حذف X-Powered-By و Server)، RequestSizeLimit (413)، SetLocale (session→query→Accept-Language negotiation)، TraceContext (W3C traceparent)، EncryptCookies، StartSession، ShareErrorsFromSession، CsrfVerify (419)، Cors
- **TVN-0021 Router**: Compiled Trie (static→param→wildcard با specificity قطعی)، `Route` fluent (name/middleware/where*/defaults/domain)، inline regex و binding-field در uri، `RouteRegistrar` + nested group، `resource`/`apiResource`، fallback (GET-only مثل Laravel)، domain routing با `{tenant}`/`*`، `UrlGenerator` (route/url/asset/current/previous + Signed URL با expires و HMAC)، `ControllerResolver` (تزریق Request/route params با cast به int/enum + container)، Route Cache (`route:cache` با reject Closure)، ReDoS guard دقیق، تطبیق Unicode (اسلاگ فارسی)، 405 با Allow header
- **TVN-0022 Session** (نسبی): `Session` (get/put/flash/reflash/keep/oldInput/regenerate/invalidate/token/previousUrl)، درایورهای `file` (flock+atomic+GC lottery+0600) و `cookie` (stateless رمزنگاری‌شده) و `array`، Session Hardening: ID با CSPRNG، رد ID نامعتبر (fixation)، idle/absolute timeout، fingerprint (IP+UA)، JSON-only serialization (ضد Object Injection)، کوکی HttpOnly+SameSite=Lax+Secure(prod)؛ **CSRF-ready** با توکن و hash_equals
- **TVN-0023 Cookie**: `Cookie` VO (پیش‌فرض‌های امن)، `CookieJar` (make/forever/queue/forget)، `EncryptCookiesMiddleware` (decrypt ورودی/encrypt خروجی؛ tamper → null بی‌صدا)
- **TVN-0024 View**: موتور `.tv.php` — `TemplateCompiler` شش‌pass با **پارسر دستی directive** (بدون regex بازگشتی → بدون JIT-stack exhaustion؛ ایمیل/`@@`/`@{{}}` امن)، auto-escape همهٔ `{{ }}` (ENT_QUOTES|ENT_HTML5 + fast-path ۰٫۲µs)، `{!! !!}` raw، `Escaper` (html/attr/js JSON_HEX/url/css) + `SafeString`، layout (@extends/@section/@yield تودرتو)، @push/@stack، @include/If/When/First، **Component** (`<x-alert :prop="$x">` + `<x-slot>` + `@props` + `ComponentAttributeBag`)، @csrf/@method/@json/@trans/@choice/@can/@verbatim/@php، directive سفارشی، `ViewFinder` با override سلسله‌مراتبی و namespace ماژول‌ها، کش کامپایل با hash(path|mtime|version)، محافظ Path Traversal و max-depth، `view:cache` با verify
- **TVN-0025 I18n**: `Translator` (dot-key با namespace `shop::`، placeholder، fallback chain fa_IR→fa→en، missing-key log)، `choice()` با فرم‌های `{0}|{1}|[2,*]` و index-based، `PluralRules` (fa/en/ar/ru)، Accept-Language negotiation، `PersianFormatter` (ارقام/جداکنندهٔ فارسی، minor-units پول)، helpers: `trans()/__()/trans_number()/jalali()/is_rtl()`، lang files هسته در fa+en (۱۹ دامنه پیام)
- **TVN-0026 Jalali**: پورت **breaks-based jalaali-js** (دقیق‌تر از فرمول ۲۸۲۰‌سالهٔ مستند — اصلاحیه در DOC-070 ثبت شد)، Julian Day bridge، `JalaliDate` (immutable: parse با ارقام فارسی، format مثل date() با ارقام فارسی/لاتین، add/sub/diff/start-endOfMonth/dayOfYear/weekOfYear)، نام ماه‌ها (ایرانی/افغان) و روزها، Hijri табular؛ **۱۰۰٪ تطبیق با intl (۸۰۰۰ نمونه) + ۱۰۰۰۰ roundtrip + همهٔ vectors**
- **TVN-0027 CORS**: preflight OPTIONS→204، reflect origin، `Vary: Origin`، wildcard `*.example.com`، خطای پیکربندی برای `*`+credentials
- **TVN-0054 Crypt (زودهنگام — پیش‌نیاز cookie/session)**: AES-256-GCM با قالب `v{n}:iv:ct:tag`، `KeyRing` با versioning/rotation، HKDF (با fallback RFC 5869)، HMAC + verify ثابت‌زمان، AAD binding، `key:generate`
- **Console**: `serve` (php -S با router script خارج از public)، `route:list/cache/clear/match`، `view:cache/clear`، discovery خودکار دستورات موتورها
- **Entry/دمو**: `public/index.php` کامل (handle→send→terminate)، `bin/server-router.php`، `routes/web.php` نمونه (welcome/hello یونیکد/time جلالی/session counter/xss-test/fallback 404)، layout+views فارسی RTL، بنچمارک‌های router/kernel/view

### تغییر یافت
- `ResponseInterface`: تفکیک `header()` (getter) از `withHeader()` (setter) + `send(): void` (اصلاحیهٔ DOC-014 — الگوی sendAndTerminate)
- `Router::fallback` فقط GET/HEAD (حفظ ۴۰۵ خالص — مانند Laravel)
- `ExceptionHandler`: انتشار هدرهای HttpException (Allow) روی پاسخ؛ CliRenderer با status واقعی
- gate کیفیت: `proc_open` آرایه‌ای به‌جای shell_exec برای lint ویوها؛ استخراج کلیدهای `__` قبل از extract در رندر ویو

### نتایج
- ۳۰۲ تست / ۷۷۹ assertion سبز • سربار درخواست ۰٫۱ms • route match ۲٫۴µs/۱۰۰۰ روت • compile ویو ۰٫۴۳ms • ۴۱۱ فایل lint پاک • gate ممنوعه‌ها سبز

## [0.1.0] - 2026-10-15 — فاز M1: هستهٔ صفر‌وابستگی

### افزوده شد
- **TVN-0001** Autoloader خودنوشته: PSR-4 + Wildcard (`engines/*/src`) + ClassMap authoritative + امنیت path-traversal + `core/bootstrap.php` (DOC-027)
- **TVN-0002** Container: Auto-Wiring با ReflectionCache (dump/load)، Contextual Binding، Singleton/Scoped/Factory/Transient، Tagging تنبل، Extender، تشخیص چرخه با مسیر، Union/DNF قطعی، Method Injection (`call`)، LazyProxy بدون eval، ScopeManager
- **TVN-0003** Config: EnvLoader بدون putenv، EnvRepository (اولویت $_SERVER→.env→default)، Repository با dot-notation + LRU + freeze + masking، ConfigLoader با اولویت engine<module<app، ConfigCacher اتمیک، SchemaValidator
- **TVN-0004** Event: EventDispatcher با Priority قطعی، Wildcard Segment-Trie، Subscriber، Hierarchy (interface/parent)، Stoppable، Allowlist امنیتی، Fake/Assert برای تست (Outbox در M3)
- **TVN-0005** Pipeline: الگوی Onion عمومی، پارامتر (`auth:web,admin`)، alias، terminate، max_depth
- **TVN-0006** Error: ExceptionHandler (report/render/map/renderable/dontReport/level)، ErrorHandler (E_* → ErrorException)، Deprecation→CI failure، LogMasker، RefCodeGenerator (Crockford base32)، Renderers: JSON RFC 9457 / HTML امن / CLI / Debug غنی
- **TVN-0007** Log: LogManager چندکاناله (daily/json/audit/query/queue/security/deprecations/stderr/stack/null/array)، LogRecord readonly، Processorها (mask/trace/identity/memory/runtime/backtrace/environment)، Buffer، چرخش روزانه با retention، sanitization علیه Log Injection
- **TVN-0008** Compat: Compat (report/driver/require/forceFallbacks)، Utf8، **Decimal بدون bcmath** (دقت ۱۸ رقم — تطبیق با Python Decimal)، ZipWriterPure، MagicBytesDetector، jsonValidate fallback
- **TVN-0009** Optimizer: Cacherها (config/autoload)، Optimizer با زمان‌بندی، atomic write
- **TVN-0010** Module: ManifestParser با schema validation، ModuleRegistry (کشف engines+modules)، TopologicalSorter (Kahn قطعی)، ModuleManager (enable/disable با پایداری وضعیت)
- **TVN-0011** Console: SignatureParser، ArgvInput، Output رنگی (NO_COLOR)، Command (ask/confirm/choice/table)، ConsoleKernel با discovery، `bin/tvn` + ۱۳ دستور داخلی
- **Test Engine (حداقلی)**: TestCase + Assertionها + expectException + TestRunner بومی (`tvn test`)
- Application/ApplicationBuilder + ۸ Bootstrapper (idempotent) + Facadeها (App/Config/Event/Log/Module) + ServiceProvider
- قراردادها: Container/Config/Events/Log/Pipeline/Console/Support در `core/Contracts/`
- سلسله‌مراتب استثناها (DOC-013) با کدهای TCR/TCF/TEV/TKR/...
- ابزار کیفیت: `tools/scripts/lint.sh`، `check-forbidden.php` (gate موارد ممنوعه)، بنچمارک container و autoload
- ساختار پروژه مطابق DOC-080 + `tavaan.json` + `.env.example` + manifest موتورهای ۱۱گانه

### نتایج
- ۱۵۵ تست / ۳۶۲ assertion سبز • بوت ~۷٫۷ms • classmap ۹۰٫۶٪ سریع‌تر • صفر eval/putenv
