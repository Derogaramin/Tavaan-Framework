# موتور بهینه‌سازی و کش — `Optimizer`

> موتور `optimizer` فریمورک تَوان.
>
> مستند کامل: [OPTIMIZER](../../docs/03-engines/optimizer.md) • تیکت: TVN-0009 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.optimizer` |
| namespace | `Tavaan\Engine\Optimizer\` |
| Provider | `Tavaan\Engine\Optimizer\OptimizerServiceProvider` |
| وابستگی سخت | `config`، `console` |
| فرمان‌های CLI | `tvn optimize`، `tvn optimize:clear`، `tvn config:cache`، `tvn config:clear`، `tvn autoload:cache` |
| آمار | 6 فایل / ~254 خط در src • 0 فایل تست (0 متد) |

## شروع سریع

```bash
# این موتور تست محلی ندارد — پوشش غیرمستقیم از راه سوئیت سراسری
# و تست موتورهای همسایه (مثل router در engines/http/tests)
php bin/tvn test

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/optimizer/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/optimizer.md`](../../docs/03-engines/optimizer.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
