# تَوان | Tavaan

فریمورک PHP 8.2+ **صفر‌وابستگی**، فارسی‌اول و ماژولار — بدون یک پکیج خارجی در زمان اجرا.

> پیاده‌سازی بر اساس مستندات رسمی: [Tavaan-Doc](https://github.com/Derogaramin/Tavaan-Doc)
> وضعیت فعلی: **فاز M1 (هسته) + M2 (وب) + M3 (داده) + M4 (امنیت/هویت) — تکمیل و سبز** ✅ — CRUD کامل با DB + ورود/نقش/JWT/2FA/ممیزی ضدتقلب بالا آمده است

---

## ویژگی‌ها

- 🚫 **صفر وابستگی** (ADR-0004): بدون `vendor/`، بدون Composer در runtime — Autoloader خودنوشته (ADR-0005)
- 🇮🇷 **فارسی‌اول**: تقویم جلالی، ارقام فارسی، پیام‌های دوزبانه، Masking دادهٔ ایرانی (کارت/موبایل/کدملی)
- 💉 **Container کامل**: Auto-Wiring با ReflectionCache، Contextual Binding، Scoped/Factory، Union/DNF قطعی، تشخیص چرخه با مسیر کامل
- ⚙️ **معماری موتور‌محور**: ۵۳ موتور خودبسنده در `engines/` با manifest و مرزهای سخت
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
php bin/tvn serve                 # سرور توسعه → http://127.0.0.1:8000 (صفحهٔ خوش‌آمد فارسی)
```

**نیازمندی‌ها:** PHP ≥ 8.2 با حداقل افزونه‌های `pdo`, `openssl`, `json`, `hash`, `mbstring` — بقیه fallback خالص PHP دارد (موتور `compat`).

## وضعیت فازها (DOC-120)

| فاز | نام | وضعیت |
|---|---|---|
| **M1** | هستهٔ صفر‌وابستگی (container, config, event, pipeline, error, log, compat, optimizer, module, console) | ✅ **انجام شد** |
| **M2** | وب (http, router, session, cookie, view, i18n, jalali, cors) + crypt (پیش‌نیاز زودهنگام) | ✅ **انجام شد** |
| **M3** | داده (database, schema, migration, orm, pagination, validation) | ✅ **انجام شد** |
| **M4** | امنیت/هویت (crypt, hash, auth, rbac, security, cache, audit) | ✅ **انجام شد** |
| M5..M9 | صف/سرویس‌ها/دامنه/ابزار/اپلیکیشن‌ها | — |

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
| ساخت Trie از ۱۰۰۰ روت | ≤ ۱۵ms | ✅ ۸٫۰ms |
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
| تست‌های M1 | سبز | ✅ (مجموع فعلی: ۵۴۴ تست / ۲۷۳۶ assertion) |
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

## دستورات CLI موجود (M1+M2)

`list` `about` `version` `env` `doctor` `optimize` `optimize:clear` `config:cache` `config:clear` `autoload:cache` `module:list` `event:list` `test` `serve` `route:list` `route:cache` `route:clear` `route:match` `view:cache` `view:clear` `key:generate` `migrate` `migrate:rollback` `migrate:status` `migrate:fresh` `migrate:refresh` `migrate:install` `cache:clear` `rbac:sync` `audit:verify` `audit:export` `audit:prune` `audit:show` `event:outbox-relay`

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
```

قواعد کد: `docs/01-standards/` — هر فایل با `declare(strict_types=1)`، یک کلاس در هر فایل، استثنا با `messageId` (نه متن خام)، پول = int minor-unit.

## پروانه

MIT — نگارش 0.4.0 (پیش از 1.0؛ API ممکن است بر اساس ADRها تکامل یابد)
