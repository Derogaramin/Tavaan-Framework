# تَوان | Tavaan

فریمورک PHP 8.2+ **صفر‌وابستگی**، فارسی‌اول و ماژولار — بدون یک پکیج خارجی در زمان اجرا.

> پیاده‌سازی بر اساس مستندات رسمی: [Tavaan-Doc](https://github.com/Derogaramin/Tavaan-Doc)
> وضعیت فعلی: **فاز M1 (هستهٔ صفر‌وابستگی) — تکمیل و سبز** ✅

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
php -S 127.0.0.1:8000 -t public   # (تا M2: صفحهٔ خوش‌آمد)
```

**نیازمندی‌ها:** PHP ≥ 8.2 با حداقل افزونه‌های `pdo`, `openssl`, `json`, `hash`, `mbstring` — بقیه fallback خالص PHP دارد (موتور `compat`).

## وضعیت فازها (DOC-120)

| فاز | نام | وضعیت |
|---|---|---|
| **M1** | هستهٔ صفر‌وابستگی (container, config, event, pipeline, error, log, compat, optimizer, module, console) | ✅ **انجام شد** |
| M2 | وب (http, router, session, cookie, view, i18n, jalali, cors) | ⏳ بعدی |
| M3 | داده (database, schema, migration, orm, pagination, validation) | — |
| M4 | امنیت/هویت (crypt, hash, auth, rbac, security, cache, audit) | — |
| M5..M9 | صف/سرویس‌ها/دامنه/ابزار/اپلیکیشن‌ها | — |

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
| تست‌های M1 | سبز | ✅ ۱۵۵ تست / ۳۶۲ assertion |
| صفر `eval`/`putenv` | ✅ | ✅ (gate خودکار) |

> ⚠️ Outbox/Inbox موتور Event به موتور database (M3) وابسته است و در M3 فعال می‌شود.

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

## دستورات CLI موجود (M1)

`list` `about` `version` `env` `doctor` `optimize` `optimize:clear` `config:cache` `config:clear` `autoload:cache` `module:list` `event:list` `test`

## توسعه

```bash
bash tools/scripts/lint.sh              # php -l روی همهٔ فایل‌ها
php tools/scripts/check-forbidden.php   # gate موارد ممنوعه (DOC-010)
php bin/tvn test                        # Native Test Runner
php engines/container/benchmarks/container.bench.php
php tools/scripts/bench-autoload.php
```

قواعد کد: `docs/01-standards/` — هر فایل با `declare(strict_types=1)`، یک کلاس در هر فایل، استثنا با `messageId` (نه متن خام)، پول = int minor-unit.

## پروانه

MIT — نگارش 0.1.0 (پیش از 1.0؛ API ممکن است بر اساس ADRها تکامل یابد)
