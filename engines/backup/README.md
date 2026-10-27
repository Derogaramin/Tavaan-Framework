# موتور پشتیبان‌گیری — `Backup`

> M5 — DB dump خالص (بدون mysqldump، chunked با cursor → حافظهٔ ثابت)، ZIP خالص (ZipWriterPure + ZipReader خودنوشته)، 🔒 رمزنگاری AES-256-GCM با کلید جدا از APP_KEY، verify (hash+decrypt)، restore با --force + maintenance mode، retention/prune.
>
> مستند کامل: [BACKUP](../../docs/03-engines/backup.md) • تیکت: TVN-0068 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.backup` |
| namespace | `Tavaan\Engine\Backup\` |
| Facade | `Tavaan\Core\Facade\Facades\Backup` (accessor: `backup`) |
| پیکربندی | `config/backup.php` → `config('backup.*')` |
| Provider | `Tavaan\Engine\Backup\BackupServiceProvider` |
| وابستگی سخت | `config`، `filesystem` |
| وابستگی نرم | `database`، `crypt`، `cache`، `compat` |
| فرمان‌های CLI | `tvn backup:run`، `tvn backup:list`، `tvn backup:verify`، `tvn backup:restore`، `tvn backup:prune` |
| آمار | 13 فایل / ~1,794 خط در src • 1 فایل تست (11 متد) |

## شروع سریع

```bash
# تست‌های موتور (11 متد)
php bin/tvn test engines/backup

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Backup;

// API عمومی و مثال‌ها: docs/03-engines/backup.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/backup/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/backup.md`](../../docs/03-engines/backup.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
