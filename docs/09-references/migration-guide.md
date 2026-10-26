# DOC-132 — راهنمای مهاجرت (Migration Guide) 🧭

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-132 |
| نقش | مهاجرت نسخه‌ای (upgrade) + مهاجرت پذیرش (از سایر فریمورک‌ها به تَوان) |
| مرتبط | DOC-081E (موتور upgrade)، DOC-004 (Quick Start)، DOC-120 (معیارهای انتشار) |

---

## ۱. مهاجرت نسخه‌ای (۰٫x → ۱٫۰ و بعد از آن)

### ۱٫۱ ابزار رسمی — موتور upgrade (DOC-081E)

```bash
php bin/tvn upgrade:status            # نسخهٔ جاری + مسیر ارتقا + آخرین backup
php bin/tvn upgrade:check --to=1.0.0  # بررسی سازگاری (PHP/افزونه/فضا) — بدون اجرا
php bin/tvn upgrade --to=1.0.0        # ارتقای کامل (backup اجباری 🔒)
php bin/tvn upgrade:rollback --force  # بازگشت از آخرین backup (در صورت شکست)
```

جریان خودکار هر ارتقا:

```
Pre-check → Backup (DB+files) → maintenance (tvn down)
→ مسیر پله‌ای نسخه‌ها (هر گام: migrations + data patches + config migration + cache clear)
→ health check → tvn up     [شکست در هر گام → rollback]
```

### ۱٫۲ قراردادها

| قاعده | توضیح |
|---|---|
| **SemVer** | minor = افزودنی سازگار؛ major = breaking + ADR + به‌روزرسانی این راهنما |
| **Data Patch** | تغییرات داده بین نسخه‌ها در `upgrades/<version>/patch_*.php` (idempotent، اجراشده‌ها در DB ثبت می‌شوند — `upgrade:patches`) |
| **Config migration** | کلیدهای جدید/تغییرنام‌یافته در هر گام خودکار منتقل می‌شوند؛ `tvn env:check` دریفت کلیدهای ENV را گزارش می‌دهد |
| **Deprecated → حذف** | هر رفتار منسوخ دست‌کم یک minor زودتر با `E_DEPRECATED` + کلید `TVN_FAIL_ON_DEPRECATION` در CI اعلام می‌شود (DOC-010) |
| **کیت‌ها** | `tvn kit:install <kit>` idempotent است — پس از ارتقای هسته، اجرای مجدد preset/theme را به‌روز می‌کند |

### ۱٫۳ مسیر ۰٫۱۶ → ۱٫۰

انتشار ۱٫۰ **بدون شکست API** نسبت به ۰٫۱۶ برنامه‌ریزی شده است (همهٔ
موتور/ماژول‌ها `@api 1.0` دارند). گام‌های پیشنهادی پذیرش ۱٫۰:

1. `tvn upgrade:check --to=1.0.0` → رفع موارد گزارش‌شده (PHP ≥ 8.2، افزونه‌ها، فضا)
2. `tvn test` (سوئیت اپ) + `tvn arch:check` + `tvn security:scan` سبز
3. `tvn upgrade --to=1.0.0`
4. `tvn optimize` (کش config+autoload+route) و راستی‌آزمایی `health_check` کیت‌ها

## ۲. مهاجرت پذیرش — از Laravel/Symfony به تَوان

### ۲٫۱ نگاشت مفاهیم (Laravel → تَوان)

| Laravel | تَوان | یادداشت |
|---|---|---|
| `composer require` / vendor | **هیچ** — صفر‌وابستگی (ADR-0004) | Autoloader خودنوشته؛ بدون composer در runtime |
| Service Container | `core/Container` + Facadeها (`Tavaan\Core\Facade\Facades\*`) | Auto-Wiring + Contextual + Lazy |
| Eloquent / Query Builder | موتور `database`/`orm` (`DB::table`، Model، Repository) | پول **int minor-unit** (ADR-0013) نه decimal |
| Blade | موتور `view` — قالب `.tv.php` | `@extends/@section/@if/{{ }}/@verbatim` + auto-escape |
| Artisan | `bin/tvn` (موتور console) | `make:*`، `kit:install`، `upgrade`، … |
| Middleware | موتور `http` (pipeline) | `AuthenticateMiddleware`، `ThrottleMiddleware`، … |
| Policies/Gates | موتور `rbac` + `config/rbac.php` اعلامی | Policy ماژول‌ها declarative ثبت می‌شود |
| Migrations | موتور `migration` (`tvn migrate`) | پیشوند ماژول/موتور + lock |
| Queues/Jobs | موتور `queue` | درایورهای DB/redis/sync |
| Notifications | موتور `notification` | mail (SMTP خودنوشته)/database/sms |
| Lang/Localization | موتور `i18n` + `jalali` | **فارسی-اول**، تقویم جلالی دقیق |
| Packages (spatie/…) | **موتورها/ماژول‌ها/پلاگین‌ها** | مرز سخت ARCH-001..008 |

### ۲٫۲ گام‌های مهاجرت اپلیکیشن موجود

1. **دامنه را فهرست کنید**: موجودیت‌ها/جداول → نگاشت به ماژول‌ها
   (blog/shop/crm/… آماده‌اند — `tvn kit:list`).
2. اسکلت تَوان را از Quick Start (DOC-004) بالا بیاورید؛ کیت‌های متناظر را
   نصب کنید: `php bin/tvn kit:install shop --with-demo`.
3. **داده**: از migrationهای خودِ کیت‌ها استفاده کنید؛ دادهٔ موجود را با
   data patch (الگوی §1.2) یا اسکریپت ETL به جداول `module_*` منتقل کنید.
   پول‌ها به **int minor-unit** تبدیل شوند (ریال: ×۱).
4. **کد**: Controllerها → `modules/<m>/src/Http`، منطق دامنه →
   `Domain/`، کوئری‌ها → پورت Repository + آداپتر DB (الگوی hexagonal
   ماژول‌های موجود را کپی کنید — CRM/ERP مرجع‌اند).
5. قالب‌ها: Blade → `.tv.php` (۹۵٪ نحوی یکسان؛ `@verbatim` برای بلوک CSS/JS).
6. **مرزها را پاس کنید**: `tvn arch:check` — ماژول فقط Core/Facade/Contract
   ماژول هم‌خانواده (ARCH-003).
7. تست: PHPUnit → `Tavaan\Testing\TestCase` بومی (`tvn test`)؛ الگوها در
   `modules/*/tests` (Feature با `MakesHttpRequests` + `RefreshDatabase`).
8. استقرار: `tvn optimize` + `tvn serve`/nginx-fpm؛ cron → `tvn schedule:run`.

### ۲٫۳ تفاوت‌های کلیدی که غافلگیر می‌کنند

- **بدون vendor**: هیچ `composer install` در استقرار نیست — کل runtime در
  ریپو است؛ پلاگین‌های ثالث فقط در `plugins/<x>/vendor` (امضاشده —
  `plugins_signature_required`).
- **float ممنوع برای پول** (Auto-Reject — DOC-113 §7): همه‌جا int minor-unit.
- **زمان فقط با `Clock`** (منبع یگانه — تست‌پذیر با `Clock::setTestNow`).
- **رویدادهای بین‌ماژولی فقط رشته‌ای/Outbox** (ADR-0009) — بدون import کلاس
  ماژول دیگر.
- پیام‌های خطا **کلید ترجمه** هستند نه متن خام (`messageId` + lang fa/en).

## ۳. وضعیت ۱٫۰

معیارهای انتشار و وضعیت راستی‌آزمایی‌شده:
`docs/reports/release-1.0-readiness.md` (و DOC-120 §6).
