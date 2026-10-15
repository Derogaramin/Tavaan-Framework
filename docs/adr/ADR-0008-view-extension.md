# ADR-0008 — پسوند فایل View: `.tv.php`

| متادیتا | مقدار |
|---|---|
| شماره | ADR-0008 |
| وضعیت | **Accepted** |
| تاریخ | 2026-10-01 |
| سطح اثر | Engine (view) |
| مرتبط | DOC-043 |

## ۱. زمینه
باید پسوند فایل View مشخص شود تا با Blade (`.blade.php`)، Twig (`.twig`) و PHP خام اشتباه نشود.

## ۲. تصمیم
پسوند **`.tv.php`** (Tavaan View). سینتک: `{{ }}` (escape)، `{!! !!}` (raw)، `@directive`.
کامپایل به PHP خالص در `storage/framework/views/`.

## ۳. گزینه‌ها
| گزینه | مزیت | عیب |
|---|---|---|
| `.blade.php` | آشنا | تداخل با Laravel، کپی‌برداری |
| `.twig` | آشنا | نیازمند parser سنگین Twig |
| **`.tv.php` (انتخابی)** | هویت مستقل، PHP-aware در IDE | نیاز به آموزش |
| `.php` خام | ساده | بدون auto-escape، خطر XSS |

## ۴. پیامدها
- IDE با `intelephense.files.associations` (DOC-082 §7.2).
- کامپایلر ۶ پاس (DOC-043 §4).
- Auto-Escape پیش‌فرض (امنیت).

## ۵. اعتبارسنجی
- 🧪 ۰ XSS در Fuzzing (AC-VIEW-02).
- 🧪 کامپایل/رندر با کارایی هدف (AC-VIEW-03).
