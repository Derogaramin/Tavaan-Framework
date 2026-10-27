# موتور گزارش ساختاریافته — `Log`

> موتور `log` فریمورک تَوان.
>
> مستند کامل: [LOG](../../docs/03-engines/log.md) • تیکت: TVN-0007 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.log` |
| namespace | `Tavaan\Engine\Log\` |
| Facade | `Tavaan\Core\Facade\Facades\Log` (accessor: `log`) |
| پیکربندی | `config/log.php` → `config('log.*')` |
| Provider | `Tavaan\Engine\Log\LogServiceProvider` |
| وابستگی سخت | `config` |
| وابستگی نرم | `event`، `queue`، `database` |
| فرمان‌های CLI | `tvn log:tail`، `tvn log:clear`، `tvn log:stats` |
| آمار | 20 فایل / ~1,426 خط در src • 1 فایل تست (11 متد) |

## شروع سریع

```bash
# تست‌های موتور (11 متد)
php bin/tvn test engines/log

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Log;

// API عمومی و مثال‌ها: docs/03-engines/log.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/log/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/log.md`](../../docs/03-engines/log.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
