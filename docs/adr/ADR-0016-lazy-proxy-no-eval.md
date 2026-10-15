# ADR-0016 — Lazy Proxy بدون eval

| متادیتا | مقدار |
|---|---|
| شماره | ADR-0016 |
| وضعیت | **Accepted** |
| تاریخ | 2026-10-01 |
| سطح اثر | Kernel (container) |
| مرتبط | DOC-026 §7 |

## ۱. زمینه
برای سرویس‌های سنگین (DB Connection، Search Client) که ممکن است در هر درخواست لازم نشوند،
Lazy Proxy نیاز است. اما ساخت Proxy با `eval` ممنوع است (اصل P8، امنیت).

## ۲. تصمیم
Lazy Proxy با **`__call`/`__get` magic** روی یک کلاس `LazyProxy` عمومی، بدون `eval`.
برای Interfaceهای تایپ‌شده که نیاز به `implements` واقعی دارند، دو راه:
1. کلاس Proxy از پیش‌نوشته برای Interfaceهای پرکاربرد (`LazyCacheStore implements CacheStoreInterface`).
2. تولید فایل Proxy در `storage/cache/proxies/` با `file_put_contents` + include یک‌باره (نه eval).

## ۳. گزینه‌ها
| گزینه | مزیت | عیب |
|---|---|---|
| eval برای ساخت Proxy | انعطاف | ممنوع (امنیت، P8) |
| **`__call` + Proxy از پیش‌نوشته/تولیدی (انتخابی)** | بدون eval | کمی boilerplate |
| بدون Lazy Proxy | ساده | بارگذاری همهٔ سرویس‌ها (اتلاف حافظه) |

## ۴. پیامدها
- `Container::lazy()` برای سرویس‌های سنگین.
- `config/container.php` → `lazy_proxies.targets`.
- تولید Proxy در `optimizer` (فاز M9).

## ۵. اعتبارسنجی
- 🧪 Lazy Proxy فقط در اولین دسترسی هدف را Resolve می‌کند.
- 🧪 صفر `eval` در codebase.
