# ADR-0007 — ORM دوگانه (Active Record + Repository)

| متادیتا | مقدار |
|---|---|
| شماره | ADR-0007 |
| وضعیت | **Accepted** |
| تاریخ | 2026-10-01 |
| سطح اثر | Engine (orm) |
| مرتبط | DOC-050 |

## ۱. زمینه
دو فلسفهٔ متضاد: Active Record (ساده، سریع برای توسعه) در برابر Repository + Domain Object
(تمیز، قابل تست، آمادهٔ استخراج سرویس). پروژه‌های مختلف نیازهای متفاوت دارند.

## ۲. تصمیم
تَوان **هر دو** را پشتیبانی می‌کند. هر ماژول در `module.json` → `orm_style` یکی را انتخاب می‌کند:
- `active-record`: برای `mvc`/`hmvc` و پروژه‌های کوچک.
- `repository`: برای `hexagonal` و ماژول‌های قابل استخراج.

## ۳. گزینه‌ها
| گزینه | مزیت | عیب |
|---|---|---|
| فقط Active Record | ساده | دامنه به DB چسبیده، تست سخت |
| فقط Repository | تمیز | Boilerplate زیاد برای پروژهٔ کوچک |
| **هر دو (انتخابی)** | انعطاف | دو مسیر برای مستند/تست |

## ۴. پیامدها
- Unit of Work کامل (Doctrine-style) در نسخهٔ ۱ نیست (پیچیدگی/کارایی) → در آینده با ADR جدید.
- `Model` base class برای Active Record؛ Domain Object خالص برای Repository.
- Mapper در Infrastructure.

## ۵. اعتبارسنجی
- 🧪 یک ماژول با هر دو سبک پیاده و تست شود (AC-ORM-04).
