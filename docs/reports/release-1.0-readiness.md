# گزارش آمادگی انتشار 1.0 — راستی‌آزمایی معیارهای DOC-120 §6

| متادیتا | مقدار |
|---|---|
| تاریخ | 2026-10-26 (پس از گام M9-۸ / کیت BPMS) |
| نگارش | 0.16.0 |
| محیط راستی‌آزمایی | Debian 12 sandbox — PHP 8.2.35، ~۱GB RAM، SQLite، بدون opcache (CLI) |
| ابزارها | `tvn test/bench:all/arch:check/lint:php/security:scan/module:validate/engine:validate/kit:install/kit:list/audit:verify` + smokeهای HTTP واقعی |

> ⚠️ اعداد کارایی/پوشش روی **سخت‌افزار sandbox** (CPU اشتراکی/دیسک کند/۱GB
> RAM) اندازه‌گیری شده‌اند — خود bench نیز به وابستگی سخت‌افزاری تصریح دارد.
> معیار نهایی انتشار، اندازه‌گیری روی سخت‌افزار مرجع است.

---

## ۱. وضعیت معیارها

| # | معیار (DOC-120 §6) | وضعیت | شواهد |
|---|---|---|---|
| 1 | همهٔ ۵۳ موتور پیاده و تست‌شده | ✅ | ۵۲ manifest (engine:validate ✓) + `pagination` ادغام‌شده در `database` طبق DOC-030 ردیف ۲۳ • ۱۲۲۳ تست موتورها سبز (اجرای scoped) |
| 2 | Coverage هسته ≥۹۵٪ / موتورها ≥۹۰٪ | ❌ **شکاف** | اندازه‌گیری xdebug: موتورها **۷۰٫۱٪** (۲۳۹۷۸/۳۴۱۸۲ خط) • core **۶۵٪** (اجرای engines) / ۵۸٫۹٪ (اجرای app) — جزئیات §۳ |
| 3 | اهداف کارایی DOC-112 | ⚠️ | `bench:all`: **۰ ❌** و ۲ ⚠️ سخت‌افزار-وابسته — جزئیات §۴ |
| 4 | صفر آسیب‌پذیری High/Critical | ✅ | `security:scan`: high=0 • ۸ یافتهٔ med بازبینی شد — همه checksum غیرامنیتی/کلید cache (§6) |
| 5 | همهٔ ۸ کیت نصب‌شدنی | ✅ | هر ۸ کیت با `--with-demo` روی **DB مشترک** نصب + هم‌زیستی: smoke ۱۸/۱۸ (§۵) |
| 6 | مستندات کامل (docs.tavaan.dev) | ⚠️ | ۱۲۱ سند در ریپو + manifest ماشین‌خوان کامل؛ **میزبانی وب** بیرون از دامنهٔ ریپو باقی است |
| 7 | `tvn` CLI کامل | ✅ | همهٔ گروه‌های فرمان (api/arch/audit/backup/bench/cache/config/db/engine/env/event/kit/lint/make/migrate/module/optimize/plugin/queue/rbac/report/schedule/search/security/serve/test/upgrade/workflow + فرمان‌های ماژول‌ها) |
| 8 | راهنمای مهاجرت + Quick Start | ✅ | Quick Start: DOC-004 • راهنمای مهاجرت: **DOC-132** (نسخه‌ای با `tvn upgrade` + پذیرش از Laravel/Symfony) — این گام افزوده شد |
| 9 | Benchmark در برابر Laravel/Symfony | ⬜ | نیازمند composer/vendor + شبکه — در sandbox صفر‌وابستگی ممکن نیست؛ bench داخلی در برابر اهداف DOC-112 انجام شد (§۴) |

**جمع‌بندی: ۵ معیار ✅، ۲ معیار ⚠️ (باز-اندازه‌گیری/میزبانی)، ۱ معیار ❌
(پوشش کد — شکاف واقعی)، ۱ معیار ⬜ (بنچمارک خارجی).**

---

## ۲. سلامت عمومی (پس از M9 کامل)

- سوئیت تست: **۱۶۷۰ تست / ۰ failure** (۱۰٬۹۵۶ assertion) — ۱۰ خطای محیطی
  `proc_open` در `QaToolsTest` فقط زیر فشار حافظهٔ sandbox (اجرای جدا: ۱۰/۱۰ سبز)
- `lint:php` ✓ ۲۲۰۱ فایل • `arch:check` ✓ ۱۵۷۲ فایل/۰ نقض • `module:validate` ✓ ۶۴ • `engine:validate` ✓ ۵۲
- ۶۴ manifest فعال (۵۲ موتور + ۱۲ ماژول) • ۸ کیت • ۶۳/۶۳ تیکت بک‌لاگ done

## ۳. پوشش کد — اندازه‌گیری واقعی و شکاف

روش: `tvn test --coverage` با xdebug 3.2 (mode=coverage). اجرای کامل سوئیت
با coverage در ۱GB RAM ممکن نشد (OOM) → دو اجرای scoped:

| اجرا | تست‌ها | نتیجه |
|---|---|---|
| `test --coverage engines` (تست موتورها) | ۱۲۲۳ | TOTAL ۷۰٫۱٪ • core ۶۵٫۰٪ • بهترین‌ها: pipeline ۹۳٫۶٪، hash ۸۸٫۹٪، i18n ۸۵٫۴٪، admin ۸۴٫۹٪، security ۸۴٫۴٪ • کمترین‌ها: migration ۰٪*، console ۱۷٫۷٪، optimizer ۳۳٪ |
| `test --coverage --app` (تست‌های سراسری) | ~۳۶۰ | core ۵۸٫۹٪ (مکمل اجرای بالا — union بالاتر است ولی ابزار merge ندارد) |

\* پوشش ۰٪ موتور migration در این اجرا artifact است: تست‌های Migrator در
`tests/` سراسری‌اند (اجرای app) نه engines — union دو اجرا عدد واقعی است.

**تفسیر صادقانه:** هدف DOC-110 §9 (هسته ≥۹۵٪ / موتورها ≥۹۰٪ خط) با اعداد
فعلی **برآورده نیست** — این شکاف پیش از M9 وجود داشته و با تست‌های M9
(۸۷ تست BPMS شامل E2E) کوچک‌تر شده اما بسته نشده. بستن آن یک اسپرینت
تست‌نویسی اختصاصی می‌خواهد (تمرکز: console/migration/optimizer/media/tenancy
و شاخه‌های خطای core). توصیه: پیش از تگ 1.0، معیار را یا برآورده کنید یا
در DOC-110 هدف واقع‌بینانهٔ فاز‌بندی‌شده تعریف شود (مثلاً 1.0: ≥۸۰٪،
1.x: ≥۹۰٪).

## ۴. کارایی — bench:all (DOC-112)

| bench | ❌ | ⚠️ | نکات |
|---|---|---|---|
| container | 0 | 0 | ۱۰۰۰ make() → ۰٫۳۱۵ms (هدف ≤۱ms) |
| kernel | 0 | 1 | **per-request overhead ۰٫۱۲۴–۰٫۱۳ms ✅** (هدف ≤۳ms) • RPS تئوری ~۷۷۰۰–۸۱۰۰ • ⚠️ cold-boot بدون opcache ۴۴–۵۴ms (هدف ≤۲۵ms) — یک‌بار در استقرار، نه per-request |
| router | 0 | 1 | match static/param/wildcard ۲–۳µs ✅ (اهداف ۵–۱۵µs) • ⚠️ cache load+match (۱۰۰۰ روت) ۱۱٫۶۶ms (هدف ≤۱٫۵ms) — I/O-محور روی دیسک sandbox |
| orm hydrate | 0 | 0 | ۱۰۰۰ مدل ≤۲۵ms ✅ |
| report | 0 | 0 | xlsx ۱۰k ردیف ۱۱۰ms (≤۳۰۰۰) • csv-streaming ۱۰۰k ردیف overhead ۰MB (≤۵MB) ✅ |
| view render | 0 | 0 | کامپایل ۰٫۳۹ms (≤۳) • رندر ۰٫۱۵ms (≤۰٫۵) • escape خصمانه ۷٫۳µs (≤۱۰) ✅ |
| workflow | 0 | 0 | start ۱۳µs • fire ۷µs (≤۳۰۰µs) ✅ |
| autoload | 0 | 0 | classmap **۹۲٪ بهبود** (هدف ≥۷۰٪) ✅ |

هر دو ⚠️ از نوع «یک‌بار/IO-محور» و وابسته به سخت‌افزارند (خروجی bench نیز
تصریح می‌کند) — با opcache+preload و دیسک واقعی انتظار پاس شدن دارند؛
**بازاندازه‌گیری روی سخت‌افزار مرجع** در چک‌لیست انتشار می‌ماند.

## ۵. هم‌زیستی ۸ کیت (معیار #5) — smoke واقعی

`php bin/tvn kit:install <kit> --with-demo` برای هر ۸ کیت روی **یک** DB
مشترک (SQLite) بدون خطا؛ سپس `tools/scripts/smoke-all-kits.php` روی
`php -S` با **نشست کوکی‌دار واقعی**:

- ۶ صفحهٔ health_check عمومی (blog/news/shop/forum/lms/crm) → ۲۰۰
- ۹ داشبورد پنل (admin/blog/news/shop/forum/lms/crm/erp/**bpms**) با نشست admin → ۲۰۰
- دادهٔ دمو همهٔ کیت‌ها حاضر: users=4، posts=4، articles=4، products=6،
  threads=3، courses=2، contacts=5، **erp_accounts=33**، bpms: 2 تعریف/5 نمونه
- `tvn_process_instances=5` (نمونه‌های workflow: crm/lms/erp/bpms) •
  `audit:verify` **سالم** پس از همهٔ نصب‌ها
- نتیجهٔ smoke کیت BPMS به‌تنهایی: **۲۸/۲۸** (جریان زندهٔ فرایند + نقش‌ها + CSV/SVG)

## ۶. بررسی ۸ یافتهٔ `med` امنیت — انجام شد ✅

`security:scan` هشت مورد md5/sha1 را برای بازبینی علامت می‌زند. بررسی موردی
(۲۰۲۶-۱۰-۲۶): **همه checksum غیرامنیتی یا کلید cache هستند** — هیچ‌کدام
برای رمز/توکن/امضا استفاده نمی‌شوند (دستهٔ مجازِ خود اسکنر):

| مکان | کاربرد | حکم |
|---|---|---|
| test/Mock/ProxyGenerator | شناسهٔ کلاس proxy از hash امضا (ابزار تست) | ✓ غیررمزنگاری |
| notification/AnonymousNotifiable | کلید پایدار routeها برای rate-limit/assert | ✓ غیررمزنگاری |
| schedule/ScheduleEvent ×2 | نام mutex زمان‌بندی | ✓ غیررمزنگاری |
| security/Security | کلید cache پیکربندی sanitizer | ✓ غیررمزنگاری |
| realtime/WebSocketFrame | **accept-key handshake طبق RFC 6455** (الزام پروتکل) | ✓ استاندارد |
| api/IdempotencyMiddleware | کلید cache idempotency (از hash، نه ورودی خام) | ✓ غیررمزنگاری |
| api/ThrottleMiddleware | کلید cache throttle (hash هویت — بدون نشت شناسهٔ خام) | ✓ غیررمزنگاری |

## ۷. اقدامات باقی‌مانده تا تگ 1.0

1. **پوشش کد** (§3) — اسپرینت تست‌نویسی یا بازتعریف هدف فازبندی‌شده در DOC-110
2. **بازاندازه‌گیری bench + coverage روی سخت‌افزار مرجع** (opcache روشن)
3. **میزبانی docs.tavaan.dev** (خروجی مستندات ریپو — manifest ماشین‌خوان آماده است)
4. **بنچمارک مقایسه‌ای Laravel/Symfony** (نیازمند محیط دارای vendor)
