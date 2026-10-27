# موتور ظرف سرویس — `Container`

> موتور `container` فریمورک تَوان.
>
> مستند کامل: [CONTAINER](../../docs/03-engines/container.md) • تیکت: TVN-0002 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.container` |
| namespace | `Tavaan\Engine\Container\` |
| Facade | `Tavaan\Core\Facade\Facades\App` (accessor: `Tavaan\Core\Contracts\Container\ContainerInterface`) |
| پیکربندی | `config/container.php` → `config('container.*')` |
| Provider | `Tavaan\Engine\Container\ContainerServiceProvider` |
| فرمان‌های CLI | `tvn container:graph` |
| آمار | 14 فایل / ~1,608 خط در src • 1 فایل تست (27 متد) |

## شروع سریع

```bash
# تست‌های موتور (27 متد)
php bin/tvn test engines/container

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\App;

// API عمومی و مثال‌ها: docs/03-engines/container.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/container/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── benchmarks/      # بنچمارک (اهداف DOC-112)
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/container.md`](../../docs/03-engines/container.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
