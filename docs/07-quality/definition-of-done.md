# DOC-113 — تعریف «انجام‌شده» (Definition of Done) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-113 |
| نقش | دروازهٔ نهایی پذیرش هر تیکت/موتور/ماژول |
| اصل | اگر همهٔ بندها تیک نخورد، کار **تمام نیست** |

---

## ۱. DoD عمومی (هر تیکت)

### ۱٫۱ کد
- [ ] `declare(strict_types=1)` در همهٔ فایل‌های جدید
- [ ] `php -l` بدون خطا
- [ ] PHPStan level max بدون error (بدون Baseline جدید)
- [ ] PHP-CS-Fixer بدون diff
- [ ] صفر `E_DEPRECATED` در PHP 8.2/8.3/8.4
- [ ] بدون `eval`/`goto`/`global`/dynamic property
- [ ] همهٔ Property/Param/Return تایپ‌دار
- [ ] کلاس‌های هسته `final` (مگر با ADR)
- [ ] بدون `require` به vendor/پکیج خارجی (در core/engines)

### ۱٫۲ تست
- [ ] Unit test برای هر کلاس/متد عمومی
- [ ] Coverage حوزه مطابق DOC-110 §9 (≥ ۹۰٪ موتورها، ≥ ۹۵٪ هسته)
- [ ] تست خطاها (نه فقط مسیر موفق)
- [ ] Data Provider برای حالت‌های متعدد
- [ ] تست‌ها Deterministic (بدون وابستگی به ترتیب/زمان/random بدون seed)
- [ ] Fake به‌جای I/O واقعی

### ۱٫۳ مستندات
- [ ] مستند موتور/ماژول به‌روز (طبق DOC-015)
- [ ] PHPDoc کامل برای API عمومی (`@param`, `@return`, `@throws`, `@api`)
- [ ] کلیدهای ترجمه در `lang/fa` و `lang/en`
- [ ] مثال اجراشدنی در `examples/`
- [ ] بلوک machine-readable به‌روز

### ۱٫۴ امنیت
- [ ] ورودی‌ها Validate شده
- [ ] خروجی‌ها Escape شده
- [ ] مجوز (Gate/Policy) برای عملیات حساس
- [ ] دادهٔ حساس در لاگ Mask شده
- [ ] بدون Secret هاردکد
- [ ] کوئری‌ها Prepared (بدون الحاق رشته)
- [ ] رویداد امنیتی در Audit ثبت می‌شود

### ۱٫۵ کارایی
- [ ] بدون N+1
- [ ] Benchmark برای مسیرهای داغ (در صورت نیاز)
- [ ] بدون regression > ۵٪ (DOC-112 §6)

---

## ۲. DoD موتور (Engine) — افزوده بر §1

- [ ] `module.json` معتبر (`tvn engine:validate <name>`)
- [ ] ServiceProvider با `register()` بدون side effect
- [ ] همهٔ Contractها در `core/Contracts/` با `@api`
- [ ] `config/<name>.php` با پیش‌فرض کامل + کامنت
- [ ] Facade (در صورت نیاز) با `@mixin` و `@method`
- [ ] Helperها با `function_exists` محافظت‌شده
- [ ] همهٔ MessageIdها کلید ترجمه دارند
- [ ] `README.md` در پوشهٔ موتور با شروع سریع
- [ ] `benchmarks/<name>.bench.php` با اهداف عددی
- [ ] معیارهای پذیرش (AC-*) در مستند → تست
- [ ] ثبت در رجیستری (`docs/03-engines/README.md`)
- [ ] بدون وابستگی به موتورهای فاز بالاتر
- [ ] Fallback برای هر افزونهٔ اختیاری

---

## ۳. DoD ماژول (Module) — افزوده بر §1

- [ ] `module.json` کامل (DOC-022 §3)
- [ ] ساختار پوشه مطابق DOC-022 §2
- [ ] مرز ماژول رعایت شده (فقط `Contracts` عمومی است)
- [ ] `tvn arch:check` بدون نقض (ARCH-001..008)
- [ ] جداول با prefix ماژول
- [ ] بدون Join/FK بین‌ماژولی
- [ ] Migration با `down()`
- [ ] Seeder برای دادهٔ اولیه
- [ ] مجوزها در manifest و sync شده
- [ ] روت‌ها با `name` و `middleware`
- [ ] Viewها با namespace ماژول
- [ ] رویدادهای عمومی با Schema + Version
- [ ] تست Feature برای جریان اصلی
- [ ] مستند در `modules/<name>/docs/`

---

## ۴. DoD اپلیکیشن/کیت (Kit) — افزوده بر §1-3

- [ ] `kit.json` معتبر
- [ ] همهٔ ماژول‌های لازم نصب و فعال
- [ ] Theme کامل با RTL
- [ ] سناریوهای E2E (DOC-09x) سبز
- [ ] دادهٔ نمونه (demo) قابل نصب
- [ ] نقش‌ها و مجوزها seed شده
- [ ] `tvn kit:install <kit>` بدون خطا
- [ ] Wizard نصب (اختیاری)
- [ ] مستند کاربری + راهنمای admin

---

## ۵. چک‌لیست نهایی Merge (PR Gate)

```
┌─ کد ────────────────────────────────────────┐
│ ☐ php -l        ☐ PHPStan max    ☐ CS-Fixer │
│ ☐ strict_types  ☐ No Deprecated  ☐ No vendor│
├─ تست ───────────────────────────────────────┤
│ ☐ Unit سبز      ☐ Feature سبز    ☐ Coverage │
│ ☐ Security تست  ☐ Arch تست       ☐ Bench    │
├─ مستند ─────────────────────────────────────┤
│ ☐ موتور/ماژول   ☐ PHPDoc         ☐ lang fa/en│
│ ☐ machine-readable  ☐ مثال       ☐ README   │
├─ امنیت ─────────────────────────────────────┤
│ ☐ Validate ورودی ☐ Escape خروجی ☐ Policy    │
│ ☐ Mask لاگ       ☐ Prepared SQL  ☐ Audit    │
└─────────────────────────────────────────────┘
```

---

## ۶. تعریف «آموزش» برای ایجنت

یک ایجنت وقتی می‌تواند ادعای «تمام» کند که:
1. همهٔ بندهای §1 مربوط به نوع کار (موتور/ماژول/اپ) تیک خورده باشد.
2. `tvn test` و `tvn arch:check` و `tvn security:scan` سبز باشند.
3. معیارهای پذیرش (AC-*) مستند موتور پاس شده باشند.
4. Diff پوشش کد نسبت به قبل ≥ ۰ باشد (کاهش نداشته باشد).

---

## ۷. موارد رد فوری (Auto-Reject) 🔒

| مورد | دلیل |
|---|---|
| نبود `declare(strict_types=1)` | DOC-010 |
| `require` به vendor در core/engines | اصل P1 |
| `eval`/`unserialize` ناامن | DOC-016 |
| SQL با الحاق رشته | NFR-SEC |
| رشتهٔ خام فارسی در کد (بدون کلید ترجمه) | NFR-UX-03 |
| تست حذف‌شده یا skip بدون دلیل | DOC-110 |
| کاهش coverage | DOC-113 §6 |
| نقض مرز ماژول | DOC-022 |
| `float` برای پول | ADR-0013 |
| Deprecated در PHP 8.4 | DOC-010 §2 |
