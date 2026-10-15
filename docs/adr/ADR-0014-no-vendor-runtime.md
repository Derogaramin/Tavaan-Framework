# ADR-0014 — صفر vendor در زمان اجرا

| متادیتا | مقدار |
|---|---|
| شماره | ADR-0014 |
| وضعیت | **Accepted** |
| تاریخ | 2026-10-01 |
| سطح اثر | Global |
| مرتبط | ADR-0004، DOC-082 |

## ۱. زمینه
باید مرز دقیقی بین «زمان اجرا» و «زمان توسعه» برای وابستگی‌ها باشد.

## ۲. تصمیم
- **زمان اجرا (production)**: صفر `vendor/`. فقط `core/`, `engines/`, `modules/`, `app/`.
- **زمان توسعه (dev)**: `require-dev` برای PHPStan/CS-Fixer/PHPUnit/Infection مجاز است.
- **پلاگین‌ها**: می‌توانند `plugins/<x>/vendor/` داشته باشند (معافیت، ایزوله).

## ۳. گزینه‌ها
| گزینه | مزیت | عیب |
|---|---|---|
| vendor در همه‌جا | ساده | نقض صفر‌وابستگی |
| **صفر vendor در runtime (انتخابی)** | امن، آفلاین، سبک | ابزار dev جدا |

## ۴. پیامدها
- `.gitignore`: `/vendor/` (فقط dev).
- `tools/` جدا از `core/engines`.
- Package production بدون `vendor/`, `tools/`, `tests/`.

## ۵. اعتبارسنجی
- 🧪 `mv vendor vendor.bak && tvn test` سبز (DOC-082 §4.2).
- 🧪 Package production بدون vendor نصب و اجرا می‌شود.
