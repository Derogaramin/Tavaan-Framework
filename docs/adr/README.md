# شاخص تصمیم‌های معماری (ADR Index)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-140 |
| نقش | نمای ناوبری همهٔ تصمیم‌های معماری تَوان |
| قاعده | هر تصمیم برگشت‌ناپذیر = یک ADR (قالب در `docs/templates/adr-template.md`) |

---

## ۱. فهرست کامل ADRها

> هر ADR فایل مستقل خودش را دارد: `docs/adr/ADR-NNNN-<slug>.md`.

| شماره | عنوان | وضعیت | اثر | فایل |
|---|---|---|---|---|
| [ADR-0001](ADR-0001-combined-architecture.md) | معماری ترکیبی (HMVC + Modular + ED + Hexagonal) | ✅ Accepted | Global | `ADR-0001-combined-architecture.md` |
| [ADR-0002](ADR-0002-licensing.md) | مدل مجوزدهی (MIT هسته / تجاری Enterprise) | 🟡 Proposed | Global | `ADR-0002-licensing.md` |
| [ADR-0003](ADR-0003-php-min-version.md) | حداقل نسخهٔ PHP = 8.2 | ✅ Accepted | Global | `ADR-0003-php-min-version.md` |
| [ADR-0004](ADR-0004-zero-dependency.md) | صفر وابستگی Composer در هسته | ✅ Accepted | Global | `ADR-0004-zero-dependency.md` |
| [ADR-0005](ADR-0005-custom-autoloader.md) | Autoloader خودنوشته با ClassMap Cache | ✅ Accepted | Kernel | `ADR-0005-custom-autoloader.md` |
| [ADR-0006](ADR-0006-primary-key-ulid.md) | شناسهٔ اصلی: ULID | ✅ Accepted | Data | `ADR-0006-primary-key-ulid.md` |
| [ADR-0007](ADR-0007-dual-orm.md) | ORM دوگانه (Active Record + Repository) | ✅ Accepted | Engine (orm) | `ADR-0007-dual-orm.md` |
| [ADR-0008](ADR-0008-view-extension.md) | پسوند فایل View: `.tv.php` | ✅ Accepted | Engine (view) | `ADR-0008-view-extension.md` |
| [ADR-0009](ADR-0009-outbox-pattern.md) | Outbox الگوی پیش‌فرض رویداد ناهمزمان | ✅ Accepted | Engine (event) | `ADR-0009-outbox-pattern.md` |
| [ADR-0010](ADR-0010-multi-tenancy.md) | Multi-Tenancy با Strategy Pattern | ✅ Accepted | Engine (tenancy) | `ADR-0010-multi-tenancy.md` |
| [ADR-0011](ADR-0011-fiber-context.md) | Context با Fiber-Aware Storage | ✅ Accepted | Kernel | `ADR-0011-fiber-context.md` |
| [ADR-0012](ADR-0012-jalali-calendar.md) | تقویم جلالی خودنوشته در موتور i18n | ✅ Accepted | Engine (jalali) | `ADR-0012-jalali-calendar.md` |
| [ADR-0013](ADR-0013-money-representation.md) | پول با int minor-unit + Money VO | ✅ Accepted | Global (Domain) | `ADR-0013-money-representation.md` |
| [ADR-0014](ADR-0014-no-vendor-runtime.md) | صفر vendor در زمان اجرا | ✅ Accepted | Global | `ADR-0014-no-vendor-runtime.md` |
| [ADR-0015](ADR-0015-api-versioning.md) | نسخه‌گذاری API با /api/v1 + Header | ✅ Accepted | Engine (api) | `ADR-0015-api-versioning.md` |
| [ADR-0016](ADR-0016-lazy-proxy-no-eval.md) | Lazy Proxy بدون eval | ✅ Accepted | Kernel (container) | `ADR-0016-lazy-proxy-no-eval.md` |

---

## ۲. دسته‌بندی موضوعی

### هسته و اجرا
- ADR-0001 (معماری ترکیبی) • ADR-0005 (Autoloader) • ADR-0011 (Fiber Context) • ADR-0016 (Lazy Proxy)

### صفر‌وابستگی
- ADR-0004 (صفر Composer) • ADR-0014 (صفر vendor در runtime)

### زبان و نسخه
- ADR-0003 (PHP 8.2+)

### داده
- ADR-0006 (ULID) • ADR-0007 (ORM دوگانه) • ADR-0013 (Money)

### وب و ارائه
- ADR-0008 (`.tv.php`) • ADR-0015 (API versioning)

### رویداد و توزیع
- ADR-0009 (Outbox) • ADR-0010 (Multi-Tenancy)

### بومی‌سازی
- ADR-0012 (تقویم جلالی)

### کسب‌وکار
- ADR-0002 (مجوزدهی)

---

## ۳. نمودار وابستگی تصمیم‌ها

```
ADR-0004 (صفر وابستگی)
   ├──▶ ADR-0005 (Autoloader خودنوشته)
   ├──▶ ADR-0014 (صفر vendor در runtime)
   └──▶ ADR-0012 (تقویم جلالی خودنوشته)

ADR-0003 (PHP 8.2)
   └──▶ ADR-0013 (Money با readonly VO)

ADR-0001 (معماری ترکیبی)
   ├──▶ ADR-0007 (ORM دوگانه)
   ├──▶ ADR-0009 (Outbox)
   ├──▶ ADR-0010 (Multi-Tenancy)
   └──▶ ADR-0011 (Fiber Context)

ADR-0016 (Lazy Proxy بدون eval) ← از ADR-0004 (امنیت/صفر وابستگی)
```

---

## ۴. قواعد مدیریت ADR 🔒

1. **شمارهٔ ترتیبی هرگز تغییر نمی‌کند** — حتی اگر تصمیم لغو شود (→ `Superseded by ADR-NNNN`).
2. **ADR پذیرفته‌شده ویرایش نمی‌شود** — اصلاح با ADR جدید که قبلی را Supersede می‌کند.
3. هر ADR باید به **اصل (DOC-002)** یا **الزام (DOC-003)** ارجاع دهد.
4. وضعیت‌ها: `Proposed` → `Accepted` → (`Deprecated` | `Superseded`).
5. هر ADR باید **اعتبارسنجی (Validation)** با معیار تست‌پذیر داشته باشد.
6. ADRهای جدید در همین ایندکس ثبت می‌شوند.

---

## ۵. ADRهای پیشنهادی برای آینده (Backlog)

| شمارهٔ پیشنهادی | موضوع | فاز |
|---|---|---|
| ADR-0017 | استراتژی Cache L2/L3 (nested caching) | M4 |
| ADR-0018 | Event Sourcing کامل برای حسابداری ERP | M7 |
| ADR-0019 | Unit of Work کامل در ORM (Deferred) | M9 |
| ADR-0020 | استراتژی Sharding برای مقیاس افقی | M9 |
| ADR-0021 | قرارداد پلاگین Marketplace و Registry | M8 |
| ADR-0022 | پشتیبانی از GraphQL در موتور API | 2.0 |
