# ADR-0010 — Multi-Tenancy با Strategy Pattern

| متادیتا | مقدار |
|---|---|
| شماره | ADR-0010 |
| وضعیت | **Accepted** |
| تاریخ | 2026-10-01 |
| سطح اثر | Engine (tenancy) |
| مرتبط | DOC-028، DOC-082E، ADR-0011 |

## ۱. زمینه
کاربردهای SaaS و سازمانی نیاز به چنداجاره‌ای دارند. سه استراتژی رایج وجود دارد با
معامله‌های متفاوت (هزینه/جداسازی/مقیاس).

## ۲. تصمیم
پشتیبانی از هر سه استراتژی با **Strategy Pattern**:
- A. Shared DB + `tenant_id` (پیش‌فرض)
- B. Database-per-Tenant
- C. Schema-per-Tenant (PostgreSQL)

انتخاب با `config/tenancy.php` → `strategy`. Tenant Context به‌صورت Fiber-aware (ADR-0011).

## ۳. گزینه‌ها
| گزینه | مزیت | عیب |
|---|---|---|
| فقط Shared | ساده | جداسازی ضعیف |
| فقط DB-per-Tenant | جداسازی قوی | هزینه/مقیاس |
| **هر سه با Strategy (انتخابی)** | انعطاف کامل | پیچیدگی پیاده‌سازی |

## ۴. پیامدها
- `TenantContext` Fiber-aware (ADR-0011).
- `TenantScope` خودکار روی کوئری‌ها.
- Cache/Queue/FS isolation.
- Quota guard.

## ۵. اعتبارسنجی
- 🧪 نشت داده بین tenant = ۰ (AC-TEN-03).
- 🧪 Resident context cleanup (AC-TEN-05).
