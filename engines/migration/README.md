# موتور مهاجرت دیتابیس — `Migration`

> Migrator در engines/database/src/Migration (namespace مطابق DOC-049)؛ دستورات CLI در src/Console.
>
> مستند کامل: [MIGRATION](../../docs/03-engines/migration.md) • تیکت: TVN-0032 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.migration` |
| namespace | `Tavaan\Engine\Database\Migration\` |
| Provider | `Tavaan\Engine\Database\Migration\MigrationServiceProvider` |
| وابستگی سخت | `database`، `schema`، `console` |
| جداول | `tvn_migrations` |
| فرمان‌های CLI | `tvn migrate`، `tvn migrate:rollback`، `tvn migrate:status`، `tvn migrate:fresh`، `tvn migrate:refresh`، `tvn migrate:install` |
| آمار | 6 فایل / ~272 خط در src • 2 فایل تست (13 متد) |

## شروع سریع

```bash
# تست‌های موتور (13 متد)
php bin/tvn test engines/migration

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/migration/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/migration.md`](../../docs/03-engines/migration.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
