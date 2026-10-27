# موتور خط لوله — `Pipeline`

> موتور `pipeline` فریمورک تَوان.
>
> مستند کامل: [PIPELINE](../../docs/03-engines/pipeline.md) • تیکت: TVN-0005 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.pipeline` |
| namespace | `Tavaan\Engine\Pipeline\` |
| پیکربندی | `config/pipeline.php` → `config('pipeline.*')` |
| Provider | `Tavaan\Engine\Pipeline\PipelineServiceProvider` |
| وابستگی سخت | `container` |
| آمار | 5 فایل / ~294 خط در src • 1 فایل تست (12 متد) |

## شروع سریع

```bash
# تست‌های موتور (12 متد)
php bin/tvn test engines/pipeline

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/pipeline/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/pipeline.md`](../../docs/03-engines/pipeline.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
