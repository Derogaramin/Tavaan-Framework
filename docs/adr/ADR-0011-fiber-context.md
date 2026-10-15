# ADR-0011 — Context با Fiber-Aware Storage

| متادیتا | مقدار |
|---|---|
| شماره | ADR-0011 |
| وضعیت | **Accepted** |
| تاریخ | 2026-10-01 |
| سطح اثر | Kernel |
| مرتبط | DOC-025، DOC-028 |

## ۱. زمینه
در حالت Resident (Swoole/RoadRunner)، چندین درخواست همزمان در یک فرایند اجرا می‌شوند.
استفاده از `static` جهانی باعث نشت داده بین درخواست‌ها/tenantها می‌شود (خطر امنیتی).

## ۲. تصمیم
همهٔ Context (Request، Tenant، Trace، User، Correlation) در یک `Context` با
**Fiber-Aware Storage** نگهداری می‌شود:
- اگر Fiber فعال باشد → ذخیره در Fiber-local.
- اگر Coroutine (Swoole) فعال باشد → `Swoole\Coroutine::getContext()`.
- در غیر این صورت → static با کلید Fiber ID.

## ۳. گزینه‌ها
| گزینه | مزیت | عیب |
|---|---|---|
| static جهانی | ساده | نشت بین درخواست‌ها در Resident |
| **Fiber/Coroutine-aware (انتخابی)** | ایزوله، امن | پیچیدگی |

## ۴. پیامدها
- `Core\Context\Context` با `ContextStore`.
- `flushScoped()` در پایان هر درخواست (DOC-025 §4.1).
- `TenantContext::run()` برای ایزولاسیون (DOC-028).

## ۵. اعتبارسنجی
- 🧪 در Swoole پس از ۱۰۰۰ درخواست، نشت context = ۰ (AC-LC-03، AC-TEN-05).
