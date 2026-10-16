# Changelog — تَوان | Tavaan

فرمت: Keep a Changelog — نسخه‌گذاری: SemVer.

## [Unreleased] — ممیزی سلامت و یکپارچگی (M1–M4)

### اصلاح شد
- **Router/TrieNode (DOC-040 §12)**: بازنویسی حافظه‌بهینهٔ Route/Trie — segments فشرده (`string|RouteSegment` به‌جای آرایه‌های کلیددار)، methods به‌صورت بیت‌ماسک، پارس تنبل segments، یال‌های پارامتریک `ParamEdge`، هندلرهای برگ `HandlerEntry` (≤۲ مورد inline)، تک‌فرزند static به‌صورت inline، memo نرمال‌سازی کلیدها؛ حافظهٔ ۱۰۰۰ روت از ~۴٫۹MB (اندازهٔ واقعی؛ bench قدیمی ۱۴MB خطا گزارش می‌کرد) به **~۱٫۷MB ≤ ۲MB** کاهش یافت
- **باگ `TrieNode::staticChild`**: فرزند سوم static وقتی map دو عضوی بود در slot تک‌فرزند جا می‌ماند و با lookup جدید پنهان می‌شد (۴۰۴) — invariant «map پر ⟹ single تهی» اکنون در هر دو مسیر برقرار است + ۲ تست رگرسیون
- **باگ `Route::head` نبود**: `RouteRegistrar` متد `head` را forward می‌کرد ولی `Router::head()` وجود نداشت (fatal در زمان ثبت) — افزوده شد
- **باگ latent Enum در ControllerResolver**: `from()` روی enum غیر-backed (unit enum) و رشتهٔ عددی برای enum با backing=int → اکنون فقط `BackedEnum` resolve می‌شود با cast مناسب (سبک Laravel)
- **Contract-First (DOC-013)**: متدهای مورداستفادهٔ کد به قراردادهای موجود افزوده شد — `StoreInterface::add` (مبنای CacheLock)، `QueryBuilderInterface::updateOrInsert/orderByDesc`، `ContainerInterface::beginScope/endScope/currentScope/flushScoped` (+ delegate در `Application`)، `RequestInterface::cookies`، `ResponseInterface::hasHeader`، `UploadedFileInterface::extension`، `EncrypterInterface::encryptJson/decryptJson`، `KernelInterface::commands/resolve`
- **`key:generate`**: تأییدیهٔ بازنویسی فقط وقتی کلید قبلیِ غیرخالی وجود دارد (راه‌اندازی اولیه/CI غیرتعاملی نمی‌شکند) + اصلاح غلط تایپی توضیح («تعداد»→«تولید»)
- **`migrate:rollback --force`**: هم‌تراز با `migrate:fresh/refresh` (اجرای غیرتعاملی/CI)
- **`.env` دیگر مرده نیست**: `TVN_AUTOLOAD_CACHE` با میکرواسکن زودهنگام در `core/bootstrap.php` (مرغ‌و‌تخم classmap↔env) و `TVN_CONFIG_CACHE` از `EnvRepository` خوانده می‌شود (پیش‌تر فقط `$_SERVER` واقعی — مقدار `.env` هرگز اثر نمی‌کرد)
- **`phpstan.neon.dist` مطابق DOC-111 §2**: `bootstrapFiles`/`scanDirectories`/`universalObjectCratesClasses`/الگوی صحیح exclude (`*/tests/*`) — خطاهای `class.notFound` و همهٔ خطاهای کلاس «fatal» (`method/property/staticMethod/offsetAccess/function.notFound`) صفر شد
- **Benchهای صادق‌تر**: router.bench حافظهٔ افزایشی واقعی روت‌ها را می‌سنجد (نه کل فرایند منهای ۲MB ثابت)؛ kernel.bench هدف cold (≤۲۵ms بدون opcache) را از warm (≤۸ms با opcache) تفکیک می‌کند (PERF-BOOT-01/02)؛ render.bench هدف DOC (متن معمولی ۱KB) را از سناریوی hostile (اطلاع‌رسانی) جدا کرد
- **Docblockهای کهنه**: `EarlyErrorManager::isFatal`، `TokenManager::refresh`، `SchemaGrammar::foreignKeySql`، `Session::setFingerprint` (parameter.notFound)
- **ساختار `storage/`** مطابق DOC-040 §7 ایجاد شد (app/cache/framework/logs/backups/temp + `.gitignore` و مجوزهای مستند)

### تغییر یافت
- `CacheLock` با تایپ `StoreInterface` (به‌جای object-shape) — Contract-First
- `ModelQueryBuilder` با `@method`های صریح برای متدهای fluent فورواردشده (تحلیل ایستا/IDE)
- `Ulid`/`Base32`: `(int) bindec(...)` قطعی (حذف offsetAccess float|int)

### نتایج
- ۵۴۶ تست / ۲۷۴۶ assertion سبز • ۶۷۹ فایل lint پاک • gate ممنوعه‌ها سبز • حافظهٔ ۱۰۰۰ روت ~۱٫۷MB ✅ • boot warm ~۷٫۵–۷٫۸ms ✅ / cold ~۱۸–۲۰ms ✅ • PHPStan level max: همهٔ خطاهای کلاس fatal صفر (باقی: بدهی مستندسازی نوعِ الگوی DI)

## [0.4.0] - 2026-10-16 — فاز M4: امنیت و هویت

### افزوده شد
- **TVN-0055 Hash**: `HashManager` با **Argon2id** (64MB/t=4/threads=1) پیش‌فرض + bcrypt fallback (cost≥10 اجباری)، `needsRehash` برای ارتقای تدریجی پارامترها، `info`، Facade `Hash`
- **TVN-0060 Cache**: **۶ درایور** (array/file با flock+atomic-rename/database با UNIQUE-atomic/redis/apcu/memcached) با **fallback خودکار به file بدون crash** وقتی extension نیست، `CacheRepository` با **Stampede Protection** (lock+double-check+serve-stale+retry محدود)، **Tagging** با الگوریتم namespace (flush تگ = identifier جدید)، `CacheLock` اتمیک owner-based (release فقط توسط مالک)، TTL با Clock (تست‌پذیر)، 🔒 payload فقط JSON (ضد Object Injection — حتی فایل tamper شده = miss)، سقف max_key_length/max_value_size، `cache:clear`
- **TVN-0056 Auth**: `AuthManager` چند-Guard (web/api/admin)، `SessionGuard` با **Session Hardening** (regenerate ضد fixation، fingerprint UA+Accept-Language، چرخش توکن CSRF در login، throttle+lockout، پیام یکسان ضد User-Enumeration)، `TokenGuard` (Bearer JWT)، **`JwtEncoder` خودنوشته** با **Algorithm Pinning** (رد `alg=none` و confusion RS256→HS256؛ exp/nbf/iss/aud/jti + leeway)، `TokenManager` با **Refresh Rotation + Reuse Detection** (استفادهٔ مجدد → revoke کل family + رویداد `auth.token.reuse_detected`)، **OTP** یک‌بارمصرف (کد CSPRNG، فقط هش در cache، TTL، rate limit، masked target)، **TOTP RFC 6238** (همهٔ test vectors سبز) + `TwoFactorManager` (secret رمزنگاری‌شده، confirm، کدهای بازیابی هش‌شدهٔ یک‌بارمصرف)، `DeviceManager` (record/list/revoke با fingerprint)، `PasswordBroker` (توکن یک‌بارمصرف هش‌شده، انقضا، ضد enumeration)، `register` با هش خودکار، `logoutOtherDevices` با تأیید رمز، `DatabaseUserProvider` (model ORM یا GenericUser + 🔒 dummy-verify ضد timing leak + **rehash خودکار**)، `AuthenticateMiddleware` (JSON→401 / وب→redirect login)، جداول password_resets/personal_access_tokens/user_devices/two_factor_secrets + migration
- **TVN-0057 RBAC**: `Gate` (define/policy/before/after/allows/denies/check/any/authorize→403/forUser + نگاشت متدهای Policy)، `PermissionResolver` با **الگوریتم مؤثر** (نقش‌ها + سلسله‌مراتب با max_depth و محافظ چرخه + **wildcard** `shop.*` + **delegation** موقت) و کش (invalidate در تغییر)، `RoleManager` (create/assign/remove با انقضا و سقف مدت، grant/revoke permission، delegate/revokeDelegation)، **super-admin bypass** پیکربندی‌پذیر، `PermissionSync` از manifest ماژول‌ها (`rbac:sync`)، `HasRoles` trait (hasRole/hasAnyRole/hasPermission/permissions/can/cannot/assignRole/isSuperAdmin)، **`OwnershipScope`** (Row-Level — ناشناس → `0=1` deny-by-default؛ نشت = ۰)، ۵ جدول tvn_* + migration
- **TVN-0059 Security**: `CsrfManager` (token/field/rotate/verify با hash_equals + **Double-Submit Cookie** رمزنگاری‌شده)، `CsrfVerifyMiddleware` config-driven (except با wildcard + متدهای safe)، `RateLimiter` با **۳ الگوریتم** (Sliding-Log دقیق/Fixed Window/Token Bucket) روی cache + `ThrottleMiddleware` (**۴۲۹ با Retry-After و X-RateLimit-\***), `SecurityHeadersMiddleware` (CSP/HSTS فقط-HTTPS/nosniff/DENY/Referrer/Permissions/COOP/CORP + حذف X-Powered-By و Server)، **`HtmlSanitizer` خالص** (allowlist تگ/attribute/پروتکل، حذف on\* و javascript: حتی entity-encoded، حذف script/iframe/svg با محتوا، escape متن stray، strict escape_all)، **`SsrfGuard`** (۱۲+ CIDR مسدود شامل cloud metadata 169.254.169.254، hostnameهای محلی، IPv6 براکت‌دار، userinfo trick، بررسی **همهٔ** IPهای resolve شده ضد DNS rebinding، allowlist دامنه، **resolvePinned** برای اتصال مستقیم)، `InputNormalizer` (NFKC + حذف bidi/Trojan-Source/zero-width با **حفظ ZWNJ فارسی** + ارقام عربی/فارسی→لاتین + ی/ک + null byte)، `Security::sign/validateSignature` (سازگار با signed-URL روتر، expires)، Facade `Security`
- **TVN-0058 Audit**: `Auditor` با **زنجیرهٔ هش HMAC** (`hash[i] = HMAC(key, hash[i-1] | payload)` — payload کانونیکال)، GENESIS hash، `verify()` با cursor (حافظهٔ ثابت — ۱۰۰۰۰ رکورد سریع)، تشخیص دستکاری **و حذف** رکورد، کلید audit جدا از APP_KEY (config یا HKDF fallback)، `exclude_fields` → `[REDACTED]` پیش از hash (PII نشت نمی‌کند)، `AuditObserver` خودکار مدل‌ها (created/updated/deleted با diff دقیق از `previousOriginal`)، Attribute `#[Observed(fields:…)]`، export (خودش ممیزی می‌شود)/prune/find، CLI `audit:verify` `audit:export` `audit:prune` `audit:show`، جدول tvn_audit_logs + migration
- **Outbox (وعدهٔ M1 — ADR-0009)**: `DatabaseOutbox` روی `tvn_outbox`/`tvn_inbox` — store درون تراکنش کسب‌وکار (**rollback → ۰ رویداد**، AC-EVT-05)، claim اتمیک با owner token (**دو worker → هر رویداد یک‌بار**، AC-EVT-07)، backoff نمایی + max_attempts + failed، `releaseStale` (crash recovery)، `event:outbox-relay`
- **TVN-0022 (تکمیل نهایی)**: درایور `redis` نشست روی موتور cache (`CacheHandler` — JSON-only + TTL)
- **Facades**: `Hash` `Cache` `Auth` `Gate` `Security` `Audit` • **مثال**: `examples/auth/login-rbac.php` (خروجی کلیدی M4 — register→login→RBAC→Gate→JWT→reuse-detection→TOTP→audit tamper-detect)

### تغییر یافت
- `Crypt` اکنون رسماً `EncrypterInterface` را پیاده می‌کند (contract-first)
- `GenericUser` غیرfinal (گسترش با trait HasRoles)
- `Model`: `changes`/`previousOriginal` برای diff دقیق Observer/Audit (پس از syncOriginal هم معتبر)
- `Request`: accessor محافظت‌شدهٔ `postBag()` (برای FormRequest::merge)

### نتایج
- ۵۴۴ تست / ۲۷۳۶ assertion سبز • TOTP ۱۰۰٪ RFC 6238 • JWT alg-confusion/none رد • reuse detection + family revoke • hash-chain tamper/delete تشخیص • SSRF metadata/rebinding رد • ۶۷۶ فایل lint پاک • gate ممنوعه‌ها سبز • ۳۱/۳۱ موتور فعال

## [0.3.0] - 2026-10-16 — فاز M3: داده

### افزوده شد
- **TVN-0030 Database**: `ConnectionManager` چنداتصال (default/tenant)، `Connection` با **Lazy PDO + Reconnect خودکار** («gone away»)، Prepared Statement ۱۰۰٪ (`EMULATE_PREPARES=false` + binding تایپ‌دار null/bool/int/DateTime)، `QueryBuilder` کامل (select/join/nested-where/whereIn+subquery/whereBetween/whereExists/whereRaw/group/having/order/limit/lock/union/CTE/aggregate)، `Grammar` سه درایور (MySQL/PostgreSQL/SQLite — wrap با prefix، allowlist operator/direction)، upsert و insertOrIgnore در هر ۳ گویش، `TransactionManager` با **Savepoint تودرتو + Deadlock retry** (backoff 50/150/450ms+jitter)، `QueryLog` (slow-query + **N+1 detection** با نرمال‌سازی SQL + mask bindings)، Read/Write Splitting + sticky، chunk/chunkById/lazy/cursor (حافظهٔ ثابت)، `Ulid` (ADR-0006) در `core/Support`
- **TVN-0031 Schema**: `Blueprint` با ۳۵+ نوع ستون (ulid/uuid/enum/json/money-decimal/timestamps(precision)/softDeletes/morphs/foreignId/rememberToken)، `ColumnDefinition` سیال + freeze، `ForeignKeyDefinition` (onDelete/onUpdate با allowlist)، DDL سه درایور (MySQL inline KEY + engine/charset، PG jsonb/serial/GIN-fulltext/check-enum، SQLite numeric/check/autoincrement)، ALTER (add/drop/rename column + index؛ FK-add در SQLite با skip امن)، introspect/hasTable/hasColumn/getColumnType/getTables، dropAllTables (migrate:fresh)
- **TVN-0032 Migration**: `Migrator` با **FileLock اتمیک** (flock + TTL ضد lock مرده)، batch numbering، rollback ترتیب معکوس، dry-run، status، fresh با **guard در production** (بدون force → رد)، مسیرهای خودکار (database/ + engines/* + modules/* + addPath ماژول‌ها)، `MigrationRepository` (tvn_migrations با prefix-aware)، کشف ماژول مالک؛ CLI: `migrate` `migrate:rollback` `migrate:status` `migrate:fresh` `migrate:refresh` `migrate:install`
- **TVN-0033 ORM**: `Model`Active Record — keyType ulid/uuid/bigint با **تولید کلید خودکار**، fillable/guarded + `MassAssignmentException` در حالت strict، Castهای داخلی (int/float/bool/array/json/datetime/immutable_datetime/date/timestamp/decimal/**money:CUR** (ADR-0013)/​**encrypted**/**hashed**/enum/custom CastInterface)، accessor/mutator (`getXAttribute`/`setXAttribute`)، dirty tracking (getChanges/getOriginal/isDirty)، timestamps خودکار، **Soft Delete** (withTrashed/onlyTrashed/restore/forceDelete/trashed)، **۱۰ نوع رابطه** (HasOne/HasMany/BelongsTo/BelongsToMany با attach/detach/sync/toggle+withTimestamps/HasManyThrough/HasOneThrough/MorphOne/MorphMany/MorphTo/MorphToMany) + `MorphMap`، **Eager Loading با الگوریتم DOC-050 §4.1** (nested + constraint + با exactly ۲ کوئری — N+1=۰)، withCount (correlated subquery)، lazy loading، Observer (همهٔ رویدادها با لغو via false)، `dispatchesEvents` → Domain Event، Global/Local Scope، replicate/refresh/touch/load، toArray/toJson با hidden/visible/appends، serialize پول/enum/datetime؛ الگوی **Repository + Domain Object** (بدون extends Model — قرارداد `RepositoryInterface`)
- **TVN-0034 Pagination**: `Paginator` (page-based + meta/links استاندارد DOC-053 §5 + links() HTML RTL + clamp max_per_page=۱۰۰)، `CursorPaginator` (multi-column cursor با base64url + tie-breaker یکتا)، `keysetPaginate` (afterId)
- **TVN-0035 Validation**: **۹۲ قاعدهٔ داخلی** (فراتر از ۸۵ قاعدهٔ DOC-051): presence/type/size/format/compare/set/file/database(unique/exists/exists_multiple/unique_composite)/security(honeypot/no_html/safe_url/password/strong_password/not_compromised با blacklist constant-time)/math-money(decimal/money/percent)/advanced(prohibited/distinct/exclude_if/nested/each) + **قواعد ایرانی 🇮🇷**: iran_mobile (با نرمال‌سازی +98/98/ارقام فارسی)، iran_national_code (checksum وزن ۱۰-i)، iran_sheba (ISO 7064 mod-97)، iran_card_number (Luhn)، iran_postal_code، iran_company_id، jalali_date (کبیسه breaks-based)، persian_digits؛ wildcard expansion (`items.*.qty`)، **ReDoS guard** (طول الگو + رد nested quantifier)، allowlist شناسه در قواعد DB، MessageBag کامل، پیام‌های fa+en با variant اندازه‌ای و placeholder، FormRequest (چرخهٔ authorize→prepare→validate→422/403 + resolve خودکار در ControllerResolver)، **Attribute-based DTO validation** (`#[Required, Email, Between(18,120)]` + `Validator::forDto`)، Rule objectها (Rule::in/unique/exists) و extend
- **TVN-0022 (تکمیل)**: درایور `database` نشست — `DatabaseHandler` (JSON-only + upsert اتمیک + GC) + migration جدول `sessions`
- **Contracts (DOC-014)**: `Database\` (Connection/QueryBuilder/Grammar/TransactionManager/SchemaBuilder/Migration/Paginator)، `ORM\` (Model/Cast/Scope/Observer/Repository)، `Validation\` (Validator/Rule)، `Cache\` (Store/Lock/LockProvider/Repository)، `Auth\` (Authenticatable/Guard/StatefulGuard/UserProvider)، `Authorization\` (Gate/Response)، `Encryption\HasherInterface`
- **Facades**: `DB` `Schema` `Validator` • **مثال**: `examples/crud/blog-crud.php` (خروجی کلیدی M3 — CRUD سرتاسری با Schema/ORM/Validation/Pagination/Transaction روی SQLite)
- **بنچمارک**: `engines/orm/benchmarks/hydrate.bench.php`

### تغییر یافت
- `Request` غیرfinal شد (گسترش فقط توسط `FormRequest` — DOC-051 §5) + `fromRequestInto`/`postBag` محافظت‌شده
- `ControllerResolver`: resolve خودکار FormRequest (authorize+validate) در method injection
- `SessionManager`: درایور database فعال شد (redis در M4)

### نتایج
- ۴۴۵ تست / ۲۱۸۹ assertion سبز • Hydrate ۱۰۰۰ رکورد **۱٫۳ms** (هدف ≤۲۵ms) • حافظهٔ ۱۰۰۰ مدل ۶MB (هدف ≤۸MB) • eager loading دقیقاً ۲ کوئری (N+1=۰) • SQL Injection fuzzing: ۲۰۰ payload / ۷۲۲ assertion سبز • ۵۷۴ فایل lint پاک • gate ممنوعه‌ها سبز

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
