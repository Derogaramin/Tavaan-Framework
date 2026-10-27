# موتور چارچوب تست خودنوشته — `Test`

> M8 کامل (TVN-0084): Mock بدون eval + HttpTest درون‌فرایندی + DatabaseTest (SQLite in-memory + migrate) + Factory/Faker deterministic + Runner با group/filter/parallel/coverage. وابستگی نرم به http/database/auth/i18n (با گارد class_exists).
>
> مستند کامل: [TEST](../../docs/03-engines/test.md) • تیکت: TVN-0084 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.test` |
| namespace | `Tavaan\Testing\` |
| فرمان‌های CLI | `tvn test` |
| آمار | 22 فایل / ~3,744 خط در src • 6 فایل تست (95 متد) |

## شروع سریع

```bash
# تست‌های موتور (95 متد)
php bin/tvn test engines/test

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/test/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/test.md`](../../docs/03-engines/test.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
