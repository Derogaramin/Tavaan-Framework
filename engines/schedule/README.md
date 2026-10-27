# موتور زمان‌بندی — `Schedule`

> M5 — Cron Expression Parser خودنوشته (۵ فیلد + OR کلاسیک DOM/DOW)، Fluent API، Overlap Prevention با mutex اتمیک، onOneServer، runInBackground. فقط یک entry در crontab: schedule:run.
>
> مستند کامل: [SCHEDULE](../../docs/03-engines/schedule.md) • تیکت: TVN-0062 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.schedule` |
| namespace | `Tavaan\Engine\Schedule\` |
| Facade | `Tavaan\Core\Facade\Facades\Schedule` (accessor: `schedule`) |
| پیکربندی | `config/schedule.php` → `config('schedule.*')` |
| Provider | `Tavaan\Engine\Schedule\ScheduleServiceProvider` |
| وابستگی سخت | `config`، `console` |
| وابستگی نرم | `cache`، `queue`، `log` |
| فرمان‌های CLI | `tvn schedule:run`، `tvn schedule:list` |
| آمار | 7 فایل / ~1,326 خط در src • 1 فایل تست (22 متد) |

## شروع سریع

```bash
# تست‌های موتور (22 متد)
php bin/tvn test engines/schedule

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Schedule;

// API عمومی و مثال‌ها: docs/03-engines/schedule.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/schedule/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/schedule.md`](../../docs/03-engines/schedule.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
