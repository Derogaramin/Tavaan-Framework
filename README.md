# تَوان | Tavaan

فریمورک PHP 8.2+ **صفر‌وابستگی**، فارسی‌اول و ماژولار — بدون یک پکیج خارجی در زمان اجرا.

> پیاده‌سازی بر اساس مستندات رسمی: [Tavaan-Doc](https://github.com/Derogaramin/Tavaan-Doc)
> وضعیت فعلی: **M1–M8 تکمیل و سبز + ۵ از ۸ کیت M9** ✅ — نگارش `0.13.0` • ۶۱ manifest فعال (۵۲ موتور + ۹ ماژول) • ۱۵۰۸ تست / ۹۸۱۴ assertion • سازگار با Linux/macOS/**Windows**
> آخرین فاز: **M9 (کیت‌های اپلیکیشن)** — blog ✍️ / news 📰 / shop 🛒 / forum 💬 / **lms 🎓** — نصب با `tvn kit:install <kit> --with-demo`

---

## ویژگی‌ها

- 🚫 **صفر وابستگی** (ADR-0004): بدون `vendor/`، بدون Composer در runtime — Autoloader خودنوشته (ADR-0005)
- 🇮🇷 **فارسی‌اول**: تقویم جلالی، ارقام فارسی، پیام‌های دوزبانه، Masking دادهٔ ایرانی (کارت/موبایل/کدملی)
- 💉 **Container کامل**: Auto-Wiring با ReflectionCache، Contextual Binding، Scoped/Factory، Union/DNF قطعی، تشخیص چرخه با مسیر کامل
- ⚙️ **معماری موتور‌محور**: ۴۸ موتور پیاده‌شده (از ۵۳ موتور طراحی‌شده) در `engines/` با manifest و مرزهای سخت
- 🔒 **امن در برابر Deprecated**: قواعد سخت‌گیرانهٔ PHP 8.2 (DOC-010) + gate خودکار
- 📐 **Contract-First**: همهٔ Interfaceها در `core/Contracts/`
- 🧪 **Test Framework بومی**: بدون PHPUnit — `tvn test`

## شروع سریع

```bash
git clone <repo> tavaan && cd tavaan
cp .env.example .env
php bin/tvn about          # اطلاعات محیط
php bin/tvn doctor         # سلامت افزونه‌ها/مجوزها
php bin/tvn test           # اجرای همهٔ تست‌ها
php bin/tvn optimize       # تولید کش‌ها (config + classmap)
php bin/tvn route:list     # فهرست روت‌ها
php bin/tvn serve          # سرور توسعه
php bin/tvn serve                 # سرور توسعه → http://127.0.0.1:8000 (خوش‌آمد + دموهای M7)
php bin/tvn migrate               # جداول موتورها (queue/tenancy/workflow/payment/…)
```

> 🪟 **ویندوز**: از نگارش `0.7.1` به‌طور کامل پشتیبانی می‌شود (PathGuard برای مسیرها،
> charset ویندوزی در tenancy/backup). برای `storage:link` به Developer Mode یا ادمین نیاز است؛
> تست‌های وابسته به `stream_socket_pair`/symlink به‌صورت مستند skip می‌شوند
> (جزئیات: `docs/reports/windows-compatibility-fixes.md`).

**نیازمندی‌ها:** PHP ≥ 8.2 با حداقل افزونه‌های `pdo`, `openssl`, `json`, `hash`, `mbstring` — بقیه fallback خالص PHP دارد (موتور `compat`).

## وضعیت فازها (DOC-120)

| فاز | نام | وضعیت |
|---|---|---|
| **M1** | هستهٔ صفر‌وابستگی (container, config, event, pipeline, error, log, compat, optimizer, module, console) | ✅ **انجام شد** |
| **M2** | وب (http, router, session, cookie, view, i18n, jalali, cors) + crypt (پیش‌نیاز زودهنگام) | ✅ **انجام شد** |
| **M3** | داده (database, schema, migration, orm, pagination, validation) | ✅ **انجام شد** |
| **M4** | امنیت/هویت (crypt, hash, auth, rbac, security, cache, audit) | ✅ **انجام شد** |
| **M5** | ارتباطی/زیرساخت (queue, schedule, console, filesystem, media, mail, notification, http-client, backup) | ✅ **انجام شد** |
| **M6** | سرویس‌ها (api, observability, search, tenancy, feature-flag, realtime) | ✅ **انجام شد** |
| **M7** | دامنه (workflow, report, payment) | ✅ **انجام شد** — [گزارش](docs/reports/M7-implementation-report.md) |
| **M8** | ابزار (test, scaffold, plugin, admin, upgrade) | ✅ **انجام شد** — [گزارش](docs/reports/M8-implementation-report.md) + [ممیزی M0–M8](docs/reports/M0-M8-audit-report.md) |
| **M9** | کیت‌های اپلیکیشن (blog/news/shop/forum/lms ×۵ از ۸) + انتشار 1.0 | 🔄 **در جریان** — [blog](docs/reports/M9-blog-implementation-report.md) • [news](docs/reports/M9-news-implementation-report.md) • [shop](docs/reports/M9-shop-implementation-report.md) • [forum](docs/reports/M9-forum-implementation-report.md) • [lms](docs/reports/M9-lms-implementation-report.md) |

> گزارش‌های پیاده‌سازی: [M5](docs/reports/M5-implementation-report.md) • [M6](docs/reports/M6-implementation-report.md) • [M7](docs/reports/M7-implementation-report.md) • [M8](docs/reports/M8-implementation-report.md) • [M9: blog](docs/reports/M9-blog-implementation-report.md)/[news](docs/reports/M9-news-implementation-report.md)/[shop](docs/reports/M9-shop-implementation-report.md)/[forum](docs/reports/M9-forum-implementation-report.md)/[lms](docs/reports/M9-lms-implementation-report.md) • [سازگاری ویندوز 0.7.1](docs/reports/windows-compatibility-fixes.md) • [ممیزی M0–M8](docs/reports/M0-M8-audit-report.md)

### معیارهای پذیرش M7 (نتایج اندازه‌گیری‌شده)

| معیار | هدف | نتیجه |
|---|---|---|
| Workflow — تعریف DSL/JSON + validation | ✅ | ✅ ۷ خطای ساختاری با messageId دوزبانه |
| fire با guard رد / مجوز RBAC | ✅ | ✅ اتمیک (snapshot/restore) + `workflow.unauthorized` |
| Exclusive gateway / Parallel fork-join | ✅ | ✅ Token-based — join منتظر همهٔ توکن‌ها |
| Human Task + Timer + Sub-process + History | ✅ | ✅ claim/complete/delegate با RBAC واقعی • تایمر انسانی/ISO • پیش‌برد خودکار والد |
| Visualizer از definition | AC-WF-11 | ✅ Graphviz DOT + Mermaid (`workflow:list --diagram`) |
| Report ۱۰۰۰۰ ردیف (html) | ≤ ۲s | ✅ **۱۰۲ms** |
| xlsx ۱۰۰۰۰ ردیف (خودنوشته، بدون ext-zip) | ≤ ۳s | ✅ **۱۰۵ms** — با ZipArchive باز و اعتبارسنجی شد (AC-RPT-10) |
| CSV streaming ۱۰۰۰۰۰ ردیف | حافظه ≤ ۵MB | ✅ سربار **~0MB** (تکه‌ای + BOM + `;` + ضد CSV Injection) |
| Pivot aggregation | ≤ ۱s | ✅ **۵۶ms** (تجمیع با Decimal — بدون float) |
| Payment — purchase/verify/refund با یک Contract | ✅ | ✅ ۱۰ درگاه (زرین‌پال/آیدی‌پی/پی‌پینگ/سامان/به‌پرداخت/پارسیان/سداد/ملی/Stripe/کیف پول) |
| Callback امن (AC-PAY) | ✅ | ✅ verify با **مبلغ محلی** (Amount tampering رد) + عدم اعتماد به Status + idempotency (دو callback = یک اثر) + IPN با HMAC |
| درگاه جدید فقط با پیاده‌سازی GatewayInterface | AC-PAY-10 | ✅ `Payment::extend()` — در تست با FakeGateway تأیید شد |
| fire گردش‌کار (درایور array) | — | ✅ **۷٫۶µs/op** • start: ۱۳µs |

### معیارهای پذیرش M4 (نتایج اندازه‌گیری‌شده)

| معیار | هدف | نتیجه |
|---|---|---|
| ورود/خروج امن با session hardening | ✅ | ✅ regenerate ضد fixation + fingerprint + throttle/lockout + remember cookie HttpOnly |
| JWT با algorithm pinning | ✅ | ✅ `alg=none` و confusion (RS256→HS256) رد + exp/nbf/iss/aud/jti |
| Refresh rotation + reuse detection | ✅ | ✅ استفادهٔ مجدد → revoke کل family + رویداد |
| OTP/2FA | ✅ | ✅ OTP یک‌بارمصرف هش‌شده + TOTP با **RFC 6238 vectors** (۱۰۰٪) + کد بازیابی یک‌بارمصرف |
| RBAC با row-level scope | ✅ | ✅ سلسله‌مراتب/wildcard/delegation موقت + OwnershipScope (نشت = ۰) + deny-by-default ناشناس |
| CSRF/RateLimit/Security Headers | ✅ | ✅ ۴۱۹ + double-submit / sliding+fixed+token-bucket با ۴۲۹+Retry-After / CSP+HSTS+… |
| HTML Sanitizer (0 XSS) | ✅ | ✅ allowlist خالص + حذف on*/javascript:/entity-encoded + script با محتوا |
| SSRF Guard | ✅ | ✅ ۱۲+ CIDR مسدود + metadata cloud + DNS rebinding + userinfo + pinning |
| Audit با hash chain | ✅ | ✅ HMAC زنجیره‌ای؛ دستکاری/حذف رکورد → `audit:verify` تشخیص می‌دهد؛ ۱۰۰۰۰ رکورد ≤ ۲s |
| Cache ۶ درایور + fallback | ✅ | ✅ array/file/database/redis/apcu/memcached — بدون ext → file (بدون crash) |
| Hash Argon2id + rehash | ✅ | ✅ 64MB/t=4/threads=1 + rehash خودکار در login |
| Session driverها (TVN-0022) | کامل | ✅ database (M3) + redis روی cache (M4) |
| Outbox (وعدهٔ M1) | ✅ | ✅ tvn_outbox/tvn_inbox + claim اتمیک + backoff + `event:outbox-relay` (AC-EVT-05/07) |

### معیارهای پذیرش M3 (نتایج اندازه‌گیری‌شده)

| معیار | هدف | نتیجه |
|---|---|---|
| Query Builder معادل در ۳ درایور (MySQL/PG/SQLite) | ✅ | ✅ Grammar parity (compile-test هر ۳) + اجرای واقعی روی SQLite |
| ۰ SQL Injection در Fuzzing | ✅ | ✅ ۲۰۰ payload / ۷۲۲ assertion سبز + allowlist operator/identifier |
| Migration up/down + lock | ✅ | ✅ batch/rollback معکوس/dry-run/fresh-guard + FileLock اتمیک |
| ORM با رابطه‌ها + eager loading (۰ N+1) | ✅ | ✅ ۱۰ نوع رابطه + eager دقیقاً ۲ کوئری + nested ۳ کوئری |
| Hydrate ۱۰۰۰ رکورد | ≤ ۲۵ms | ✅ **۱٫۳ms** (با cast money/json) |
| Validation با قواعد ایرانی | ۸۵ قاعده | ✅ ۹۲ قاعده (کدملی/شبا/کارت/پستی/شناسهٔ شرکت/جلالی/ارقام فارسی) |
| CRUD کامل با DB (خروجی کلیدی M3) | ✅ | ✅ `examples/crud/blog-crud.php` سرتاسری سبز |
| Prepared Statement ۱۰۰٪ | ✅ | ✅ `EMULATE_PREPARES=false` + binding تایپ‌دار |
| Session driver دیتابیس (TVN-0022) | ✅ | ✅ DatabaseHandler + migration جدول sessions |

### معیارهای پذیرش M2 (نتایج اندازه‌گیری‌شده)

| معیار | هدف | نتیجه |
|---|---|---|
| Hello World وب (سربار هر درخواست) | ≤ ۳ms | ✅ **۰٫۱ms** (~۹۶۰۰ RPS تئوری تک‌فرایند) |
| Route match با ۱۰۰۰ روت | ≤ ۵µs | ✅ ۲٫۴µs (parametric: ۳٫۰µs) |
| ساخت Trie از ۱۰۰۰ روت | ≤ ۱۵ms | ✅ ۵٫۶ms |
| حافظهٔ ۱۰۰۰ روت + Trie | ≤ ۲MB | ✅ ~۱٫۷MB |
| Session secure + CSRF-ready | ✅ | ✅ HttpOnly/SameSite/Fingerprint/Regeneration + ۴۱۹ |
| View با auto-escape | ۰ XSS | ✅ (XSS fuzz + escape در attribute/JSON) |
| تقویم جلالی | ۱۰۰٪ دقیق | ✅ ۸۰۰۰ نمونه intl parity + ۱۰۰۰۰ roundtrip |
| کامپایل قالب ۱۰۰ خطی | ≤ ۳ms | ✅ ۰٫۴۳ms |
| رندر قالب کامپایل‌شده | ≤ ۰٫۵ms | ✅ ۰٫۲۰ms |
| ETag/304 + gzip | ✅ | ✅ |
| CORS preflight + origin allowlist | ✅ | ✅ (origin متخلف → بدون هدر) |

> ✅ هر ۵ درایور session فعال: file/cookie/array/database/redis.

### معیارهای پذیرش M1 (نتایج اندازه‌گیری‌شده)

| معیار | هدف | نتیجه |
|---|---|---|
| بوت بدون `vendor/` | ✅ | ✅ |
| `bin/tvn about` | کار کند | ✅ |
| Resolve سرویس با ۵ وابستگی | ✅ | ✅ (۳٫۹µs) |
| زمان بوت (warm) | ≤ ۸ms | ✅ ~۷٫۷ms |
| `make()` singleton | ≤ ۱µs | ✅ ۰٫۱۹µs |
| ۱۰۰۰ make متوالی | ≤ ۱ms | ✅ ۰٫۱۶ms |
| ClassMap cache | ≥ ۷۰٪ سریع‌تر | ✅ ۹۰٫۶٪ |
| Event dispatch + priority/wildcard قطعی | ✅ | ✅ |
| تست‌های M1 | سبز | ✅ (مجموع فعلی: ۵۴۶ تست / ۲۷۴۶ assertion) |
| صفر `eval`/`putenv` | ✅ | ✅ (gate خودکار) |

> ✅ Outbox/Inbox موتور Event در M3/M4 فعال شد (ADR-0009 — `event:outbox-relay`).

## ساختار

```
bin/tvn            ← CLI entry
core/              ← هسته (Contracts, Autoloading, Application, Bootstrap, Error, Facade, Support)
engines/<name>/    ← موتورهای خودبسنده (src, config, tests, module.json)
config/            ← پیکربندی سراسری + schema
app/ modules/ kits/ plugins/
storage/           ← cache/logs/framework (زمان اجرا)
tests/             ← تست‌های سراسری (Unit/Feature)
tools/scripts/     ← lint، check-forbidden، bench
docs/              ← مستندات (کپی SSOT از Tavaan-Doc)
```

## دستورات CLI (کامل — `tvn list`)

| گروه | دستورات |
|---|---|
| عمومی | `about` `version` `list` `env` `doctor` `test` `serve` `optimize` `optimize:clear` `key:generate` |
| config/autoload | `config:cache` `config:clear` `autoload:cache` |
| route/view | `route:list` `route:cache` `route:clear` `route:match` `view:cache` `view:clear` |
| داده | `migrate` `migrate:status` `migrate:rollback` `migrate:fresh` `migrate:refresh` `migrate:install` `db:tenant-guard` |
| event/queue/schedule | `event:list` `event:outbox-relay` `queue:work` `queue:listen` `queue:retry` `queue:failed` `queue:flush` `queue:prune-failed` `queue:restart` `queue:monitor` `schedule:list` `schedule:run` |
| امنیت/ممیزی | `rbac:sync` `audit:verify` `audit:show` `audit:export` `audit:prune` |
| cache/storage | `cache:clear` `storage:link` |
| ماژول | `module:list` |
| M5 (backup) | `backup:run` `backup:list` `backup:verify` `backup:restore` `backup:prune` |
| M6 | `api:docs` `search:reindex` `tenant:create` `tenant:list` `tenant:migrate` `tenant:delete` `tenant:stats` `realtime:websocket` |
| **M7** | **`workflow:timers`** (هر دقیقه با Schedule) • **`workflow:list [--diagram=]`** • **`payment:list`** |

## صفحه‌های دمو (`tvn serve`)

پس از `php bin/tvn serve` روی `http://127.0.0.1:8000`:

| مسیر | محتوا |
|---|---|
| `/` | خوش‌آمد — نگارش جاری + تازه‌های M7 |
| `/demo/report` | 📊 گزارش زندهٔ HTML (فیلتر `?status=paid`، گروه‌بندی، Pivot، جمع کل) |
| `/demo/report/csv` | 📥 دانلود CSV (BOM + `;` — آمادهٔ Excel فارسی) |
| `/demo/workflow` | 🔁 چرخهٔ کامل BPM: start → submit → claim → complete → تاریخچه + نمودار Mermaid |
| `/demo/payment` | 💳 چرخهٔ کیف پول داخلی: purchase → verify → refund (نیازمند `tvn migrate`) |
| `/payment/result` | 🧾 صفحهٔ نتیجهٔ پرداخت (مقصد callback درگاه‌ها) |
| `/payment/callback` | بازگشت کاربر از درگاه — 🔒 verify امن (خودکار ثبت‌شده) |
| `/payment/notify/{gateway}` | IPN سرور-به-سرور با بررسی امضا (خودکار ثبت‌شده) |

## توسعه

```bash
bash tools/scripts/lint.sh              # php -l روی همهٔ فایل‌ها
php tools/scripts/check-forbidden.php   # gate موارد ممنوعه (DOC-010)
php bin/tvn test                        # Native Test Runner
php engines/container/benchmarks/container.bench.php
php tools/scripts/bench-autoload.php
php engines/http/benchmarks/router.bench.php
php engines/http/benchmarks/kernel.bench.php
php engines/view/benchmarks/render.bench.php
php engines/orm/benchmarks/hydrate.bench.php
php engines/report/benchmarks/report.bench.php     # اهداف DOC-072 §9
php engines/workflow/benchmarks/workflow.bench.php
```

قواعد کد: `docs/01-standards/` — هر فایل با `declare(strict_types=1)`، یک کلاس در هر فایل، استثنا با `messageId` (نه متن خام)، پول = int minor-unit.

## پروانه

MIT — نگارش 0.8.0 (پیش از 1.0؛ API ممکن است بر اساس ADRها تکامل یابد)
