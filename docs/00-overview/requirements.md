# DOC-003 — الزامات تَوان (Requirements Specification)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-003 |
| وضعیت | Approved v1.0 |
| قالب شناسه | `<نوع>-<حوزه>-<شماره>` → `FR-HTTP-01`, `NFR-SEC-07` |
| سطوح اولویت | P0 = حیاتی (بلاکر) • P1 = مهم • P2 = مطلوب • P3 = آینده |
| قابلیت ردیابی | هر الزام در تست/موتور/ADR ارجاع می‌شود |

انواع: **FR** (الزام عملکردی) • **NFR** (الزام غیرعملکردی) • **CON** (قید/Constraint)

---

## ۱. قیود (Constraints)

| ID | قید | اولویت |
|---|---|---|
| CON-01 | زبان: PHP ≥ 8.2 (سازگار با 8.3 و 8.4 بدون Deprecated) | P0 |
| CON-02 | صفر وابستگی به پکیج Composer در `core/` و `engines/` | P0 |
| CON-03 | افزونه‌های PHP مجاز اما اختیاری؛ هر مورد نیازمند Fallback | P0 |
| CON-04 | Autoloader خودنوشته (بدون `vendor/autoload.php`) | P0 |
| CON-05 | سیستم‌عامل هدف: Linux (اولویت)، Windows/macOS (سازگاری) | P1 |
| CON-06 | وب‌سرور: Apache، Nginx، LiteSpeed، PHP Built-in، Swoole/FrankenPHP | P1 |
| CON-07 | پایگاه داده: MySQL ≥ 8.0 / MariaDB ≥ 10.6 / PostgreSQL ≥ 14 / SQLite ≥ 3.38 | P0 |
| CON-08 | کدگذاری: UTF-8 در همه‌جا؛ DB collation = `utf8mb4_0900_ai_ci` یا معادل | P0 |
| CON-09 | پروانهٔ هسته: MIT (موتورهای Enterprise قابل مجوزدهی تجاری) | P2 |
| CON-10 | کد باید با `declare(strict_types=1)` نوشته شود | P0 |
| CON-11 | هیچ اجرای شبکه‌ای/دسترسی خارجی در زمان تست‌های واحد | P1 |
| CON-12 | حجم هسته (بدون موتورها) ≤ ۱٫۵MB سورس | P2 |

---

## ۲. الزامات عملکردی هسته (FR-CORE)

| ID | الزام | اولویت | موتور/سند |
|---|---|---|---|
| FR-CORE-01 | بارگذاری خودکار کلاس‌ها بر اساس نگاشت namespace→path با کش ClassMap | P0 | `03-engines/autoloader.md` |
| FR-CORE-02 | کانتینر DI با Bind/Singleton/Instance/Factory/Contextual/Tagged | P0 | `02-architecture/container-di.md` |
| FR-CORE-03 | Resolve خودکار وابستگی‌ها با Reflection و پشتیبانی Union/Nullable/Enum/Default | P0 | ↑ |
| FR-CORE-04 | ServiceProvider با چرخهٔ register/boot/shutdown و کشف خودکار | P0 | `02-architecture/bootstrapping.md` |
| FR-CORE-05 | مخزن تنظیمات با Dot-Notation، کش، ENV casting و Schema validation | P0 | `03-engines/config.md` |
| FR-CORE-06 | بارگذاری `.env` با اولویت‌بندی و بدون PutEnv اجباری | P0 | ↑ |
| FR-CORE-07 | Event Bus با Listenerهای Sync/Async، اولویت، wildcard و Stop Propagation | P0 | `03-engines/event.md` |
| FR-CORE-08 | Pipeline/Middleware قابل ترکیب برای هر جریان (HTTP/Console/Job) | P0 | `03-engines/pipeline.md` |
| FR-CORE-09 | Facade با Lazy Proxy + `@mixin` + Test Double | P1 | `02-architecture/container-di.md` |
| FR-CORE-10 | مدیریت خطای یکپارچه: Exception→Response با Mapper و Renderable | P0 | `01-standards/error-handling.md` |
| FR-CORE-11 | کشف و ثبت ماژول‌ها با Manifest و ترتیب وابستگی (Topological Sort) | P0 | `02-architecture/modular-monolith.md` |
| FR-CORE-12 | چند Entry Point: HTTP، CLI، Worker، Scheduler، Queue از یک هسته | P0 | `02-architecture/request-lifecycle.md` |
| FR-CORE-13 | اجرای چند اپلیکیشن در یک نصب (Multi-App) با تفکیک Config/Route/Storage | P1 | `05-applications/README.md` |
| FR-CORE-14 | کش‌سازی Config/Route/View/Schema/Container با یک دستور و Invalidations | P0 | `03-engines/optimizer.md` |
| FR-CORE-15 | Opcache Preloader تولید خودکار | P2 | ↑ |

## ۳. الزامات عملکردی HTTP (FR-HTTP)

| ID | الزام | اولویت |
|---|---|---|
| FR-HTTP-01 | Router با متدهای GET/POST/PUT/PATCH/DELETE/OPTIONS/HEAD و Group/Prefix/Domain | P0 |
| FR-HTTP-02 | پارامترهای مسیری با Constraint (regex/enum/int/uuid/slug) | P0 |
| FR-HTTP-03 | Route Caching با Compiled Matcher (Trie/Regex-Chunk) | P0 |
| FR-HTTP-04 | Middleware سراسری، گروهی، مسیری و ترمینال | P0 |
| FR-HTTP-05 | Request/Response Immutable با Stream Body و PSR-7-like (بدون وابستگی) | P0 |
| FR-HTTP-06 | Cookie/Upload/Header/Query با دسترسی امن و Cast خودکار | P0 |
| FR-HTTP-07 | Negotiation محتوا: JSON/HTML/XML/CSV بر اساس Accept | P1 |
| FR-HTTP-08 | پاسخ‌های آماده: JSON، File، Download، Stream، Redirect، NoContent | P0 |
| FR-HTTP-09 | Rate Limiting با الگوریتم Token Bucket/Sliding Window و درایور Cache | P0 |
| FR-HTTP-10 | CORS با پیکربندی per-route | P0 |
| FR-HTTP-11 | HTTP Cache: ETag، Last-Modified، Conditional Request، Cache-Control | P1 |
| FR-HTTP-12 | HMR/Debug Bar در محیط توسعه | P2 |
| FR-HTTP-13 | پشتیبانی Reverse Proxy (X-Forwarded-*) با Trust Configuration | P0 |
| FR-HTTP-14 | Realtime: SSE و WebSocket Adapter | P2 |

## ۴. الزامات عملکردی داده (FR-DATA)

| ID | الزام | اولویت |
|---|---|---|
| FR-DATA-01 | Connection Manager چند‌اتصالی با Lazy Connect و Reconnect | P0 |
| FR-DATA-02 | Query Builder: Select/Join/SubQuery/Union/CTE/Window/Aggregate/Lock | P0 |
| FR-DATA-03 | Grammar اختصاصی هر درایور (MySQL/Postgres/SQLite) بدون از دست رفتن API واحد | P0 |
| FR-DATA-04 | Prepared Statement اجباری؛ Binding موقعیتی و نامی | P0 |
| FR-DATA-05 | Transaction با Nesting (Savepoint)، Deadlock Retry و Hook | P0 |
| FR-DATA-06 | ORM: Active Record + Repository (هر دو)، Eager/Lazy Loading، Relations | P0 |
| FR-DATA-07 | روابط: HasOne/HasMany/BelongsTo/BelongsToMany/MorphTo/HasManyThrough | P0 |
| FR-DATA-08 | شناسایی و هشدار N+1 در حالت توسعه | P1 |
| FR-DATA-09 | Castها: datetime، enum، json، array، money، encrypted، ulid | P0 |
| FR-DATA-10 | Migration: Create/Alter/Rename/Drop با Dry-Run و Lock | P0 |
| FR-DATA-11 | Seeder/Factory با Faker خودنوشته و Deterministic Seed | P1 |
| FR-DATA-12 | Soft Delete، Global/Local Scope، Observer (Created/Updated/Deleted) | P0 |
| FR-DATA-13 | Pagination (Page/Cursor/Keyset) با Meta استاندارد | P0 |
| FR-DATA-14 | Query Cache با Tag و TTL و Invalidations هوشمند | P1 |
| FR-DATA-15 | Chunk/Lazy/Cursor برای مجموعه‌های بزرگ و Generator-based Export | P1 |
| FR-DATA-16 | Schema Builder برای تولید DDL معکوس (Reverse Engineering) | P2 |
| FR-DATA-17 | پشتیبانی از Read/Write Splitting | P2 |

## ۵. الزامات عملکردی امنیتی/هویت (FR-SEC / FR-AUTH)

| ID | الزام | اولویت |
|---|---|---|
| FR-AUTH-01 | احراز هویت Session-based با Remember Me و Device Tracking | P0 |
| FR-AUTH-02 | احراز هویت Token-based (JWT/Opaque) با Rotation و Blacklist | P0 |
| FR-AUTH-03 | Guard چندگانه (Web/Api/Admin/Console) در یک درخواست | P0 |
| FR-AUTH-04 | ثبت‌نام، ورود، بازیابی رمز، تأیید ایمیل/موبایل (OTP) | P0 |
| FR-AUTH-05 | دو‌عاملی: TOTP، WebAuthn، Email/SMS Code | P1 |
| FR-AUTH-06 | OAuth2/OIDC Provider و Client (Google/GitHub/Keycloak) | P2 |
| FR-AUTH-07 | قفل‌شدن حساب پس از N تلاش ناموفق + Captcha | P0 |
| FR-RBAC-01 | مدل RBAC: Role ↔ Permission ↔ User + Group + Hierarchy | P0 |
| FR-RBAC-02 | مدل ABAC: Policy با Attribute/Condition/Expression | P1 |
| FR-RBAC-03 | Gate/Policy با `can()`, `authorize()`, `before/after` hooks | P0 |
| FR-RBAC-04 | مالکیت رکورد (Ownership) و Scope سطح داده (Row-Level) | P0 |
| FR-RBAC-05 | تفویض اختیار موقت (Delegation) با بازهٔ زمانی | P2 |
| FR-SEC-01 | CSRF Token با HMAC و SameSite و Rotation | P0 |
| FR-SEC-02 | XSS: Escaper زمینه‌ای (HTML/Attr/JS/URL/CSS) | P0 |
| FR-SEC-03 | رمزنگاری: AES-256-GCM + HKDF + Key Versioning | P0 |
| FR-SEC-04 | Hashing: Argon2id پیش‌فرض + Bcrypt fallback + Rehash خودکار | P0 |
| FR-SEC-05 | Random امن (CSPRNG) برای همهٔ توکن‌ها | P0 |
| FR-SEC-06 | Filesystem Sandbox و جلوگیری از Path Traversal | P0 |
| FR-SEC-07 | اعتبارسنجی آپلود: Magic Bytes + MIME + Size + Rename | P0 |
| FR-SEC-08 | SSRF Guard: Block شبکه‌های خصوصی + DNS Pinning | P0 |
| FR-SEC-09 | Security Headers: CSP، HSTS، X-Frame، Referrer-Policy، Permissions-Policy | P0 |
| FR-SEC-10 | Session Hardening: HttpOnly، Secure، Fingerprint، Idle/Absolute Timeout | P0 |
| FR-SEC-11 | Audit Log با زنجیرهٔ هش ضدتقلب | P1 |
| FR-SEC-12 | Secrets: فقط ENV/Vault؛ ممنوعیت Hardcode + Scanner در CI | P0 |
| FR-SEC-13 | Deserialization امن (Allowlist) | P0 |
| FR-SEC-14 | Masking دادهٔ حساس در لاگ (PAN، رمز، توکن، کد ملی) | P0 |

## ۶. الزامات عملکردی زیرساخت (FR-INF)

| ID | الزام | اولویت |
|---|---|---|
| FR-CACHE-01 | درایورها: Array، File، Redis، Memcached، APCu، Database | P0 |
| FR-CACHE-02 | Tag، TTL، Atomic Lock، Stampede Protection، Remember | P0 |
| FR-QUEUE-01 | درایورها: Database، Redis، File، Sync، Null + (NATS/AMQP اختیاری) | P0 |
| FR-QUEUE-02 | Job با Delay، Retry، Backoff، Timeout، MaxTries، Priority | P0 |
| FR-QUEUE-03 | Worker با Graceful Shutdown، Horizon-like Dashboard، Metrics | P0 |
| FR-QUEUE-04 | Batch/Chain/RateLimited Jobs و Unique Jobs | P1 |
| FR-EVENT-01 | Outbox Pattern برای سازگاری DB+Event | P1 |
| FR-EVENT-02 | Event Sourcing (اختیاری) با Snapshot | P2 |
| FR-LOG-01 | سطح‌ها RFC 5424، چرخش، کانال‌های چندگانه، Context/CorrelationId | P0 |
| FR-LOG-02 | خروجی: File، Stderr، Syslog، JSON، Database + Remote Adapter | P0 |
| FR-SCH-01 | Scheduler با Cron Expression Parser خودنوشته و Overlap Prevention | P0 |
| FR-MAIL-01 | SMTP/Sendmail/Log/Array/Mock + Queue + Template + Attachment + Inline | P0 |
| FR-NOTIF-01 | کانال‌ها: Mail، SMS، Push، In-App، Webhook، Telegram/Bale/Eitaa | P1 |
| FR-FS-01 | درایورها: Local، Flysystem-like abstraction، S3-compatible، FTP | P0 |
| FR-MEDIA-01 | تصویر: Resize/Crop/Watermark/WebP/AVIF با GD و Imagick و Fallback | P1 |
| FR-I18N-01 | ترجمه با Pluralization، Domain، Namespace، Fallback Chain | P0 |
| FR-I18N-02 | تقویم جلالی/قمری/میلادی + تبدیل دوطرفه دقیق | P0 |
| FR-I18N-03 | عدد/واحد/پول فارسی (RTL) و Latin | P0 |
| FR-CLI-01 | Console با Argument/Option، خروجی رنگی، Table/Progress/Ask | P0 |
| FR-CLI-02 | دستورات Scaffold (make:*) و Dev (serve, tinker, queue:work) | P0 |
| FR-API-01 | API Resource/Transformer با Pagination و Conditional Fields | P0 |
| FR-API-02 | OpenAPI 3.1 Generator از Route+Validator+Resource | P2 |
| FR-API-03 | نسخه‌گذاری URL/Header و Deprecation Policy | P1 |
| FR-OBS-01 | Metrics (Counter/Gauge/Histogram) + Prometheus Exporter | P1 |
| FR-OBS-02 | Tracing (Span) + W3C TraceContext Propagation + OTLP Export | P2 |
| FR-OBS-03 | Health Check Endpoints (liveness/readiness) | P1 |
| FR-WF-01 | موتور Workflow/BPM: State، Transition، Guard، Action، Timer | P1 |
| FR-WF-02 | تعریف فرایند با DSL/JSON و Designer Schema | P2 |
| FR-RPT-01 | Report Builder: Dataset، Filter، Group، Pivot، Aggregate | P1 |
| FR-RPT-02 | خروجی: CSV، Excel(xlsx خودنوشته)، PDF(اختیاری)، JSON | P1 |
| FR-SEARCH-01 | جستجوی Full-text با MySQL/Postgres/SQLite + Index اختصاصی | P1 |
| FR-PAY-01 | درگاه‌های پرداخت ایرانی + International با Unified Contract | P1 |
| FR-PLG-01 | سیستم پلاگین PHP با Sandbox، Manifest، Lifecycle | P1 |
| FR-TEST-01 | Test Engine خودنوشته: Assert، Mock، HttpTest، DatabaseTest | P0 |

---

## ۷. الزامات غیرعملکردی (NFR)

### ۷٫۱ کارایی (NFR-PERF)
| ID | الزام | هدف | روش سنجش |
|---|---|---|---|
| NFR-PERF-01 | زمان بوت هسته | ≤ ۸ms (warm) | `microtime` در بنچمارک |
| NFR-PERF-02 | سربار Hello World نسبت به PHP خام | ≤ ۳ms | k6 / ab |
| NFR-PERF-03 | Route Match برای ۱۰۰۰ روت | ≤ ۵µs | بنچمارک اختصاصی |
| NFR-PERF-04 | Resolve سرویس از کانتینر | ≤ ۲۰µs | بنچمارک |
| NFR-PERF-05 | حافظهٔ پایهٔ درخواست | ≤ ۴MB | `memory_get_peak_usage` |
| NFR-PERF-06 | ORM Hydrate ۱۰۰۰ رکورد | ≤ ۲۵ms | بنچمارک |
| NFR-PERF-07 | View Render (کامپایل‌شده) ۱۰۰ سطر | ≤ ۱ms | بنچمارک |
| NFR-PERF-08 | پشتیبانی از ≥ ۳۰۰۰ RPS (Swoole, 1 vCPU) | — | Load Test |
| NFR-PERF-09 | p99 latency برای CRUD ساده (با Cache) | ≤ ۵۰ms | Load Test |
| NFR-PERF-10 | بدون Memory Leak در Worker طولانی (۱M درخواست) | رشد ≤ ۵٪ | Long-run Test |
| NFR-PERF-11 | Queue Throughput | ≥ ۵۰۰۰ job/min (DB driver) | بنچمارک |
| NFR-PERF-12 | Cache Hit Ratio در سناریوی مرجع | ≥ ۹۰٪ | Metrics |

### ۷٫۲ امنیت (NFR-SEC)
| ID | الزام |
|---|---|
| NFR-SEC-01 | ۰ آسیب‌پذیری High/Critical در اسکن SAST (Psalm/PHPStan security rules) |
| NFR-SEC-02 | همهٔ ورودی‌های کاربر در Trust Boundary اعتبارسنجی شوند |
| NFR-SEC-03 | همهٔ خروجی‌ها Encode شوند (Context-Aware) |
| NFR-SEC-04 | مقایسهٔ توکن‌ها فقط با `hash_equals` |
| NFR-SEC-05 | هیچ رمز/کلیدی در سورس یا لاگ |
| NFR-SEC-06 | Session ID پس از Login/Privilege Change بازتولید شود |
| NFR-SEC-07 | پیش‌فرض `SameSite=Lax`، `HttpOnly=true`، `Secure` در PROD |
| NFR-SEC-08 | خطاهای PROD بدون Stack Trace به کاربر |
| NFR-SEC-09 | Audit برای: ورود، تغییر مجوز، تغییر دادهٔ حساس، حذف، Export |
| NFR-SEC-10 | Rate Limit روی Login/OTP/Reset/API |
| NFR-SEC-11 | پشتیبانی از Key Rotation بدون Downtime |
| NFR-SEC-12 | حداقل عمق رمز: ۱۲ کاراکتر + بررسی Blacklist |

### ۷٫۳ کیفیت و نگهداشت (NFR-QUA)
| ID | الزام | هدف |
|---|---|---|
| NFR-QUA-01 | صفر `E_DEPRECATED`/`E_STRICT` در CI با PHP 8.2/8.3/8.4 | ۰ |
| NFR-QUA-02 | PHPStan level max (با Baseline خالی) | ۰ error |
| NFR-QUA-03 | PHP-CS-Fixer با Ruleset تعریف‌شده در `01-standards/coding-style.md` | ۰ diff |
| NFR-QUA-04 | پوشش تست هسته | خط ≥ ۹۵٪ |
| NFR-QUA-05 | Mutation Score | ≥ ۸۵٪ |
| NFR-QUA-06 | پیچیدگی چرخه‌ای هر متد | ≤ ۱۰ |
| NFR-QUA-07 | طول کلاس / متد | ≤ ۳۰۰ / ≤ ۵۰ خط |
| NFR-QUA-08 | Deep Dependency (Max Nesting) | ≤ ۵ لایه |
| NFR-QUA-09 | نقض مرز ماژول | ۰ |
| NFR-QUA-10 | مستند هر موتور | ۱۰۰٪ |

### ۷٫۴ قابلیت اطمینان و دسترس‌پذیری (NFR-REL)
| ID | الزام |
|---|---|
| NFR-REL-01 | Graceful Degradation برای Cache/Mail/Queue/Search |
| NFR-REL-02 | Recovery خودکار پس از DB Reconnect |
| NFR-REL-03 | Zero-Downtime Migration Strategy (Expand-Contract) |
| NFR-REL-04 | Backup/Restore خودکار DB و Storage |
| NFR-REL-05 | هدف دسترس‌پذیری ۹۹٫۹٪ در استقرار مرجع |

### ۷٫۵ مقیاس‌پذیری (NFR-SCL)
| ID | الزام |
|---|---|
| NFR-SCL-01 | Stateless HTTP لایه (Session در Store خارجی) → Horizontal Scale |
| NFR-SCL-02 | Multi-Tenancy با سه استراتژی |
| NFR-SCL-03 | Shard-ready ID (ULID/Snowflake) |
| NFR-SCL-04 | Queue Worker مقیاس‌پذیر افقی |
| NFR-SCL-05 | Read Replica Support |

### ۷٫۶ قابلیت استفاده/دسترس‌پذیری رابط (NFR-UX)
| ID | الزام |
|---|---|
| NFR-UX-01 | RTL کامل در View Engine و Admin |
| NFR-UX-02 | WCAG 2.2 AA در خروجی پیش‌فرض Admin |
| NFR-UX-03 | i18n همهٔ پیام‌های هسته (بدون رشتهٔ خام) |

### ۷٫۷ وابستگی‌ها (NFR-DEP)
| ID | الزام |
|---|---|
| NFR-DEP-01 | `composer.json` در ریشه فقط برای autoload-dev ابزارهای QA (اختیاری) |
| NFR-DEP-02 | هسته بدون `vendor/` اجرا شود |
| NFR-DEP-03 | هر Extension استفاده‌شده در `docs/03-engines/README.md` فهرست + Fallback دارد |
| NFR-DEP-04 | ممنوعیت `eval`، `create_function`، `include` پویا از ورودی کاربر |
| NFR-DEP-05 | پلاگین‌های ثالث در `plugins/` جدا و با Sandbox |

### ۷٫۸ معماری (NFR-ARC)
| ID | الزام | اصل | روش سنجش |
|---|---|---|---|
| NFR-ARC-01 | هر قابلیت عمومی پشت یک Interface (Contract-First)؛ پیاده‌سازی قابل تعویض | P2 | `arch:check` + review |
| NFR-ARC-02 | وابستگی فقط رو به پایین؛ Domain مستقل از Infrastructure/Engine | P4 | قانون ARCH-001 |
| NFR-ARC-03 | هستهٔ کمینه: هر قابلیت حذف‌شدنی در موتور است، نه `core/` | P3 | review + `arch:check` |
| NFR-ARC-04 | مرز ماژول سخت: صفر `use` بین‌ماژولی خارج از `Contracts`؛ صفر Join/FK بین‌ماژولی | P4 | قوانین ARCH-003/006 |
| NFR-ARC-05 | HMVC: عمق Sub-Request ≤ ۴ و ایزولاسیون خطای ویجت | P4 | تست AC-HMVC-01/02 |
| NFR-ARC-06 | ارتباط بین‌دامنه‌ای با رویداد (نه فراخوانی مستقیم)؛ Outbox برای ناهمزمان | P5 | review + AC-EVT-05 |
| NFR-ARC-07 | آمادگی مایکروسرویس: ماژول بدون تغییر Domain قابل استخراج به سرویس | P4 | `tvn module:extract` |
| NFR-ARC-08 | توسعه‌پذیری سه‌گانه (Hook/Contract/Decorator) بدون Fork هسته | P12 | review + `plugin.md` |

---

## ۸. ماتریس ردیابی الزام → موتور (Traceability)

| حوزه | الزام‌ها | موتورهای مسئول |
|---|---|---|
| اجرا/بوت | FR-CORE-01..15 | autoloader، container، config، event، pipeline، optimizer |
| وب | FR-HTTP-01..14 | http، router، middleware، session، cookie، cors، ratelimit، realtime |
| داده | FR-DATA-01..17 | database، orm، migration، schema، pagination، search |
| امنیت/هویت | FR-AUTH/RBAC/SEC | auth، rbac، crypt، hash، validate، audit، security |
| زیرساخت | FR-INF | cache، queue، log، schedule، mail، notification، filesystem، media، i18n، console، api، observability، workflow، report، payment، plugin، test |

## ۹. معیار پذیرش کلان (Overall Acceptance)

- 🧪 **AC-1** نصب Offline موفق و اجرای `tvn serve` و نمایش صفحهٔ خوش‌آمد.
- 🧪 **AC-2** اجرای `tvn test` سبز با پوشش ≥ ۹۵٪ هسته.
- 🧪 **AC-3** `tvn bench:all` همهٔ اهداف NFR-PERF را پاس کند.
- 🧪 **AC-4** `tvn arch:check` و `tvn security:scan` بدون خطا.
- 🧪 **AC-5** نصب کیت Blog و Shop و اجرای سناریوی End-to-End (ثبت‌نام → خرید → پرداخت).
- 🧪 **AC-6** تبدیل ماژول `Shop` به سرویس مستقل با فقط تغییر `deploy/`.
