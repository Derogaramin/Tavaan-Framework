# ADR-0005 — Autoloader خودنوشته با ClassMap Cache

| متادیتا | مقدار |
|---|---|
| شماره | ADR-0005 |
| وضعیت | **Accepted** |
| تاریخ | 2026-10-01 |
| سطح اثر | Kernel |
| مرتبط | DOC-027، DOC-082 |

## ۱. زمینه
صورت مسئله صریحاً «بدون کامپوزر یا اتولودر [خارجی]» را خواسته. باید یک Autoloader
خودنوشته با منطق PSR-4 (بدون پکیج `composer/autoload`) ساخته شود.

## ۲. تصمیم
`Tavaan\Core\Autoloading\Autoloader` با:
- نگاشت PSR-4-like (prefix → dirs) با wildcard (`engines/*/src/`).
- ClassMap cache برای PROD (یک فایل PHP با `return []`).
- locked_prefixes برای جلوگیری از override هسته توسط ماژول/پلاگین.
- امنیت: رد نام کلاس ناامن، sandbox داخل basePath.

## ۳. گزینه‌ها
| گزینه | مزیت | عیب |
|---|---|---|
| composer/autoload | استاندارد | نقض صفر‌وابستگی |
| **Autoloader خودنوشته (انتخابی)** | صفر وابستگی، کنترل کامل | باید نوشته/تست شود |

## ۴. پیامدها
- `core/bootstrap.php` تنها require دستی.
- `tvn autoload:cache` برای تولید ClassMap.
- `composer.json` فقط برای انتشار اختیاری (DOC-082 §5).

## ۵. اعتبارسنجی
- 🧪 بوت بدون `vendor/` (AC-BOOT-01).
- 🧪 ClassMap ≥ ۷۰٪ سریع‌تر (AC-BOOT-04).
- 🧪 Path traversal → exception (AC-BOOT-03).
