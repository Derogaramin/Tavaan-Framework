# ADR-0015 — نسخه‌گذاری API با /api/v1 + Header

| متادیتا | مقدار |
|---|---|
| شماره | ADR-0015 |
| وضعیت | **Accepted** |
| تاریخ | 2026-10-01 |
| سطح اثر | Engine (api, router) |
| مرتبط | DOC-044، DOC-024 §7 |

## ۱. زمینه
API عمومی نیاز به نسخه‌گذاری دارد تا تغییرات Breaking مصرف‌کنندگان را نشکند.

## ۲. تصمیم
نسخه‌گذاری چندگانه:
- **URL prefix** (پیش‌فرض): `/api/v1/...`
- **Header** (اختیاری): `Accept: application/vnd.tavaan.v2+json`
- رویدادهای بین‌سرویسی: `event_name.vN` با Schema versioning (DOC-024 §7.2).

## ۳. گزینه‌ها
| گزینه | مزیت | عیب |
|---|---|---|
| فقط URL | ساده، قابل مشاهده | تغییر نسخه = تغییر URL |
| فقط Header | URL پایدار | کمتر قابل مشاهده |
| **هر دو (انتخابی)** | انعطاف | کمی پیچیدگی |

## ۴. پیامدها
- Deprecation policy: نسخهٔ قدیمی → header `Deprecation` + `Sunset`.
- Backward compatibility برای رویدادها (DOC-024 §7.1).

## ۵. اعتبارسنجی
- 🧪 هر دو روش versioning کار می‌کنند (AC-API-03).
