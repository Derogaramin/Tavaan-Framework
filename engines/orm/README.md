# موتور نگاشت شیء-رابطه — `ORM`

> کد در engines/database/src/ORM (namespace مطابق DOC-050 machine block). دو حالت: Active Record + Repository.
>
> مستند کامل: [ORM](../../docs/03-engines/orm.md) • تیکت: TVN-0033 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.orm` |
| namespace | `Tavaan\Engine\Database\ORM\` |
| پیکربندی | `config/orm.php` → `config('orm.*')` |
| Provider | `Tavaan\Engine\Database\ORM\OrmServiceProvider` |
| وابستگی سخت | `database`، `event` |
| وابستگی نرم | `i18n`، `crypt`، `hash` |
| آمار | 0 فایل / ~0 خط در src • 3 فایل تست (34 متد) |

## شروع سریع

```bash
# تست‌های موتور (34 متد)
php bin/tvn test engines/orm

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/orm/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── benchmarks/      # بنچمارک (اهداف DOC-112)
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/orm.md`](../../docs/03-engines/orm.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
