# گزارش پیاده‌سازی M8 — ابزار (Tools)

**تاریخ:** ۲۰۲۶-۱۰-۱۷ • **فاز:** M8 (DOC-120) • **تیکت‌ها:** TVN-0084, TVN-0077, TVN-0079, TVN-0078, TVN-0081
**نتیجه:** `۱۲۳۵ تست / ۸۲۸۹ assertion سبز` (دو اجرای پیاپی — قطعی) • ۵۲ موتور فعال • lint ۱۲۹۳ فایل پاک • gate ممنوعه‌ها پاک

---

## ۱. خلاصهٔ تیکت‌ها

| تیکت | موتور | اولویت | خروجی کلیدی | تست |
|---|---|---|---|---|
| TVN-0084 | **Test** (DOC-084E) | P0 | Native Runner کامل، Mock بدون eval، HttpTest درون‌فرایندی، DatabaseTest، Factory، Faker deterministic، `--group/--filter/--coverage/--parallel` | ۸۹ |
| TVN-0077 | **Scaffold** (DOC-077) | P1 | Stub engine خودنوشته، ۱۷ دستور `make:*`، ۲۵ قالب، idempotency + `--force/.bak`، sandbox مسیر | ۳۳ |
| TVN-0079 | **Plugin** (DOC-079) | P1 | manifest + امضای Ed25519، `plugins.lock` (SHA-256)، scanner توکنی، sandbox (autoload/vendor/capabilities)، Hook declare-محور، چرخهٔ عمر کامل | ۵۸ |
| TVN-0078 | **Admin** (DOC-078) | P2 | CRUD generator (۲۷ نوع فیلد)، validation خودکار، jalali دوطرفه، منوی پویا، widget/chart، RTL کامل، theme override | ۳۹ |
| TVN-0081 | **Upgrade** (DOC-081E) | P2 | جریان pre-check→backup→down→patch→verify→up با rollback خودکار، DataPatch idempotent، ConfigMigrator، VersionResolver گام‌به‌گام | ۲۴ |

## ۲. معیارهای پذیرش

| AC | وضعیت |
|---|---|
| AC-TEST-01..05 (Runner/HttpTest/DatabaseTest/Faker/گزارش رنگی با file:line) | ✅ همه |
| AC-SCF-01 (همهٔ `make:*` خروجی `php -l`-پاک) | ✅ — در تست‌ها روی هر فایل تولیدشده exec می‌شود |
| AC-SCF-02 (`make:module` → `module:validate` پاس) | ✅ — دستور `tvn module:validate` افزوده شد |
| AC-SCF-03 (PHPStan max) | ⚠️ PHPStan در محیط قابل نصب نبود (بدون vendor) — کد با تایپ کامل و بدون mixed بی‌دلیل نوشته شد |
| AC-PLG-01..09 (امضا/lock/scan/capability/hook/lifecycle/vendor ایزوله/بدون‌امضا→رد) | ✅ همه (T-01..T-08) |
| AC-ADM-01..04 (CRUD بدون کد/Policy/RTL/override) | ✅ همه |
| AC-UPG-01..06 | ✅ / AC-UPG-07 (zero-downtime rolling) | ⛔ زیرساخت آماده؛ به M9 موکول (در upgrade.md مستند شد) |

## ۳. باگ‌های نهفتهٔ کشف‌شده و رفع‌شده حین M8

| # | باگ | ریشه | رفع |
|---|---|---|---|
| 1 | شکست کاذب ApiTest در اجرای دوم suite | throttle با Clock فریزشده روی cache پیش‌فرض `file` → کلیدها بین اجراها «زنده» می‌ماندند | `cache.default=array` در setUp تست (هم‌راستا با Idempotency همان فایل) |
| 2 | نشت محتوای ویو بین رندرهای متوالی (M2) | `ViewFactory` singleton + `stopSection` = append بدون reset | `beginViewRender/endViewRender` در `View::render` — reset sections/pushes/slots/extends + بستن ob‌های نشتی‌کرده |
| 3 | pagination نامعین در SQLite | LIMIT/OFFSET بدون ORDER BY | default order = کلید اصلی در AdminController |
| 4 | `ConfigFrozenException` در بوت production بدون `.env` | (محیط) — با `cp .env.example .env` + `key:generate` برطرف شد | مستند در quickstart-dev پایین |

## ۴. تصمیم‌های طراحی مهم

1. **Mock بدون eval** — `ProxyGenerator` فایل proxy تایپ‌دار را در `sys_get_temp_dir()/tvn-mocks` تولید می‌کند (بازتولید کامل امضا: Union/Intersection/DNF/variadic/by-ref/enum/ثابت‌ها؛ ثابت‌های جهانی fully-qualified می‌شوند چون reflection نام namespace-joined برمی‌گرداند و handler Deprecated→Exception فریمورک fallback را می‌شکند).
2. **`Model::factory()` با resolver تزریقی** — database به testing وابسته نشد؛ `TestCase::__construct` یک‌بار resolver را ثبت می‌کند.
3. **Hook declare-محور** — پلاگین فقط به hookهای اعلام‌شدهٔ میزبان (module.json/`plugins.allowed_hooks`) متصل می‌شود؛ fire خطای پلاگین را ایزوله و timeout/memory همکاری‌کننده اعمال می‌کند.
4. **Sandbox ترتیبی** — autoload پلاگین/vendor با append ثبت می‌شود؛ prefixهای قفل‌شدهٔ هسته غیرقابل override → shadow کردن `Tavaan\*` عملاً خنثی است (تست T-07).
5. **مسیرهای generic در Admin** — ۱۰ route یک‌بار ثبت می‌شوند (نه به ازای هر resource)؛ `{resourceKey}` از registry resolve می‌شود → ثبت resource پس از بوت هم کار می‌کند.
6. **Validate-then-transform** — validation روی ورودی خام کاربر (regex جلالی…) و تبدیل پس از آن؛ فیلد اختیاریِ ارسال‌نشده skip می‌شود تا default ستون اعمال شود.
7. **BackupRunnerInterface** — Upgrader به موتور Backup مستقیم وابسته نیست → تست کامل جریان با Mock (بدون I/O).

## ۵. محیط توسعه (بازتولیدپذیر)

```bash
sudo apt-get install -y php8.2-cli php8.2-sqlite3 php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php-pcov
cp .env.example .env
php bin/tvn key:generate
php bin/tvn test                 # 1235 سبز
php bin/tvn test --parallel=4    # موازی
php -d pcov.enabled=1 -d pcov.directory=$PWD bin/tvn test --coverage
```

## ۶. وضعیت پس از M8

- موتورها: **۵۲/۵۲ فعال** (۴۸ + scaffold/plugin/admin/upgrade — موتور test از M1 موجود بود و در TVN-0084 کامل شد)
- فازهای باقی‌مانده: **M9** (۸ کیت اپلیکیشن + Release 1.0) — طبق DOC-121 §7 تیکت بعدی `TVN-0091 (Kit: Blog)` است.
