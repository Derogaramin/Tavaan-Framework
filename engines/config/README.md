# موتور پیکربندی و محیط — `Config`

> موتور `config` فریمورک تَوان.
>
> مستند کامل: [CONFIG](../../docs/03-engines/config.md) • تیکت: TVN-0003 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.config` |
| namespace | `Tavaan\Engine\Config\` |
| پیکربندی | `config/config.php` → `config('config.*')` |
| Provider | `Tavaan\Engine\Config\ConfigServiceProvider` |
| وابستگی سخت | `container` |
| فرمان‌های CLI | `tvn config:cache`، `tvn config:clear`، `tvn env:check` |
| آمار | 13 فایل / ~1,081 خط در src • 3 فایل تست (20 متد) |

## شروع سریع

```bash
# تست‌های موتور (20 متد)
php bin/tvn test engines/config

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/config/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/config.md`](../../docs/03-engines/config.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
