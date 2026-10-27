# موتور سامانهٔ فایل — `Filesystem`

> M5 — ۴ درایور (local/s3/ftp/memory). 🔒 Path Sandbox + Atomic Write + S3 SigV4 خودنوشته (بدون SDK) + presigned URL. disk چندگانه با پیکربندی filesystem.disks.
>
> مستند کامل: [FILESYSTEM](../../docs/03-engines/filesystem.md) • تیکت: TVN-0063 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.filesystem` |
| namespace | `Tavaan\Engine\Filesystem\` |
| Facade | `Tavaan\Core\Facade\Facades\Storage` (accessor: `filesystem`) |
| پیکربندی | `config/filesystem.php` → `config('filesystem.*')` |
| Provider | `Tavaan\Engine\Filesystem\FilesystemServiceProvider` |
| وابستگی سخت | `config` |
| وابستگی نرم | `crypt`، `http`، `compat` |
| فرمان‌های CLI | `tvn storage:link` |
| آمار | 13 فایل / ~2,554 خط در src • 1 فایل تست (23 متد) |

## شروع سریع

```bash
# تست‌های موتور (23 متد)
php bin/tvn test engines/filesystem

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Storage;

// API عمومی و مثال‌ها: docs/03-engines/filesystem.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/filesystem/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/filesystem.md`](../../docs/03-engines/filesystem.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
