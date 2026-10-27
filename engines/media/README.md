# موتور رسانه — `Media`

> M5 — آپلود امن (magic bytes + extension allowlist + rename تصادفی + 🔒 polyglot scan + Image Bomb protection)، پردازش تصویر gd/imagick با fallback (none → فقط ذخیره)، WebP/AVIF، variants، dedup با SHA-256، EXIF سانیتیزه‌شده (بدون GPS).
>
> مستند کامل: [MEDIA](../../docs/03-engines/media.md) • تیکت: TVN-0074 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.media` |
| namespace | `Tavaan\Engine\Media\` |
| Facade | `Tavaan\Core\Facade\Facades\Media` (accessor: `media`) |
| پیکربندی | `config/media.php` → `config('media.*')` |
| Provider | `Tavaan\Engine\Media\MediaServiceProvider` |
| وابستگی سخت | `config` |
| وابستگی نرم | `filesystem`، `database`، `compat`، `http` |
| جداول | `tvn_media` |
| آمار | 8 فایل / ~1,448 خط در src • 1 فایل تست (13 متد) |

## شروع سریع

```bash
# تست‌های موتور (13 متد)
php bin/tvn test engines/media

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Media;

// API عمومی و مثال‌ها: docs/03-engines/media.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/media/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/media.md`](../../docs/03-engines/media.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
