# DOC-152 — قالب ADR (Architecture Decision Record)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-152 |
| نقش | قالب ثبت تصمیم‌های معماری |
| قاعده | هر تصمیم برگشت‌ناپذیر = یک ADR |

---

## قالب

```markdown
# ADR-NNNN — <عنوان تصمیم>

| متادیتا | مقدار |
|---|---|
| شماره | ADR-NNNN |
| وضعیت | Proposed / Accepted / Deprecated / Superseded by ADR-XXXX |
| تاریخ | YYYY-MM-DD |
| تصمیم‌گیرندگان | @name1, @name2 |
| مرتبط | DOC-XXX, ADR-YYYY |
| سطح اثر | Kernel / Engine / Module / Global |

## ۱. زمینه (Context)
<چه مسئله‌ای وجود دارد؟ چه نیروهایی در بازی‌اند؟ چه محدودیت‌هایی؟>

## ۲. تصمیم (Decision)
<تصمیم دقیقاً چیست؟ به‌صورت فعال و قاطع بنویسید.>

## ۳. گزینه‌های بررسی‌شده (Options Considered)
| گزینه | مزایا | معایب |
|---|---|---|
| A | … | … |
| B | … | … |
| C (انتخاب‌شده) | … | … |

## ۴. دلایل انتخاب (Rationale)
<چرا این گزینه؟ به کدام اصل (DOC-002) تکیه می‌کند؟>

## ۵. پیامدها (Consequences)
### مثبت
- …
### منفی / هزینه
- …
### ریسک‌ها
- …

## ۶. انطباق (Compliance)
<چه چیزهایی باید تغییر کنند تا با این تصمیم سازگار شوند؟>

## ۷. اعتبارسنجی (Validation)
<چگونه بدانیم تصمیم درست کار می‌کند؟ معیار/تست.>
```

---

## قواعد ADR 🔒

1. **شمارهٔ ترتیبی** هرگز تغییر نمی‌کند (حتی اگر تصمیم لغو شود → `Superseded`).
2. ADRهای پذیرفته‌شده **تغییر نمی‌کنند**؛ اصلاح با ADR جدید.
3. هر ADR باید به **اصل/الزام** ارجاع دهد.
4. وضعیت `Proposed` تا زمانی که تیم تصویب نکند.
5. ADRها در `docs/adr/` با نام `ADR-NNNN-<slug>.md`.

---

## فهرست ADRهای تَوان

> هر ADR فایل مستقل خودش را دارد: `docs/adr/ADR-NNNN-<slug>.md`.
> ایندکس کامل و دسته‌بندی‌شده در `docs/adr/README.md` (DOC-140).

| شماره | عنوان | وضعیت | فایل |
|---|---|---|---|
| ADR-0001 | معماری ترکیبی (HMVC + Modular + ED + Hexagonal) | Accepted | `ADR-0001-combined-architecture.md` |
| ADR-0002 | مدل مجوزدهی (MIT هسته / تجاری Enterprise) | Proposed | `ADR-0002-licensing.md` |
| ADR-0003 | حداقل نسخهٔ PHP = 8.2 | Accepted | `ADR-0003-php-min-version.md` |
| ADR-0004 | صفر وابستگی Composer در هسته | Accepted | `ADR-0004-zero-dependency.md` |
| ADR-0005 | Autoloader خودنوشته با ClassMap Cache | Accepted | `ADR-0005-custom-autoloader.md` |
| ADR-0006 | شناسهٔ اصلی: ULID | Accepted | `ADR-0006-primary-key-ulid.md` |
| ADR-0007 | ORM دوگانه (Active Record + Repository) | Accepted | `ADR-0007-dual-orm.md` |
| ADR-0008 | پسوند فایل View: `.tv.php` | Accepted | `ADR-0008-view-extension.md` |
| ADR-0009 | Outbox به‌عنوان الگوی پیش‌فرض رویداد ناهمزمان | Accepted | `ADR-0009-outbox-pattern.md` |
| ADR-0010 | Multi-Tenancy با Strategy Pattern | Accepted | `ADR-0010-multi-tenancy.md` |
| ADR-0011 | Context با Fiber-Aware Storage | Accepted | `ADR-0011-fiber-context.md` |
| ADR-0012 | تقویم جلالی در موتور i18n | Accepted | `ADR-0012-jalali-calendar.md` |
| ADR-0013 | پول با int minor-unit + Money VO | Accepted | `ADR-0013-money-representation.md` |
| ADR-0014 | صفر vendor در زمان اجرا | Accepted | `ADR-0014-no-vendor-runtime.md` |
| ADR-0015 | نسخه‌گذاری API با /api/v1 + Header | Accepted | `ADR-0015-api-versioning.md` |
| ADR-0016 | Lazy Proxy بدون eval | Accepted | `ADR-0016-lazy-proxy-no-eval.md` |
