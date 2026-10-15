# ADR-0013 — بازنمایی پول: int minor-unit + Money Value Object

| متادیتا | مقدار |
|---|---|
| شماره | ADR-0013 |
| وضعیت | **Accepted** |
| تاریخ | 2026-10-01 |
| سطح اثر | Global (Domain) |
| مرتبط | DOC-010 §6، DOC-002 (D6) |

## ۱. زمینه
پول هرگز نباید با `float` باشد (خطای دقت باینری: `0.1 + 0.2 !== 0.3`).
`bcmath` هم یک افزونهٔ خارجی است که ممکن است موجود نباشد (نقض صفر‌وابستگی).

## ۲. تصمیم
- ذخیره در DB: `decimal(18,4)` یا `bigint` (minor unit).
- در کد: Value Object `Money` با `int $amount` (minor unit) + `Currency`.
- محاسبات: کلاس `Decimal` خودنوشته (بدون bcmath) برای دقت دلخواه.
- 🔒 ممنوعیت `float` برای پول (قانون TV-NO-FLOAT-MONEY در DOC-010).

## ۳. گزینه‌ها
| گزینه | مزیت | عیب |
|---|---|---|
| float | ساده | خطای دقت — غیرقابل قبول |
| bcmath | دقیق | وابسته به ext (نقض P1) |
| **int minor-unit + Decimal خالص (انتخابی)** | دقیق، صفر وابستگی | کمی verbosity |

## ۴. پیامدها
- `Support\ValueObject\Money` (immutable, readonly).
- `Support\Math\Decimal` با Long Arithmetic (بلوک‌های ۹ رقمی).
- Property-Based Testing برای Decimal (۱۰٬۰۰۰ حالت).
- Cast `money:IRR` در ORM.

## ۵. اعتبارسنجی
- 🧪 Decimal در برابر bcmath (وقتی موجود است) روی ۱۰٬۰۰۰ حالت یکسان.
- 🧪 `0.1 + 0.2 === 0.3` دقیقاً.
- 🧪 ممنوعیت float money در CI (AST check).
