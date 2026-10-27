# موتور قالب‌ساز tv.php — `View`

> موتور `view` فریمورک تَوان.
>
> مستند کامل: [VIEW](../../docs/03-engines/view.md) • تیکت: TVN-0024 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.view` |
| namespace | `Tavaan\Engine\View\` |
| Facade | `Tavaan\Core\Facade\Facades\View` (accessor: `view`) |
| پیکربندی | `config/view.php` → `config('view.*')` |
| Provider | `Tavaan\Engine\View\ViewServiceProvider` |
| وابستگی سخت | `event`، `i18n`، `config` |
| وابستگی نرم | `rbac`، `http`، `session` |
| فرمان‌های CLI | `tvn view:cache`، `tvn view:clear`، `tvn view:directives` |
| آمار | 12 فایل / ~1,679 خط در src • 1 فایل تست (27 متد) |

## شروع سریع

```bash
# تست‌های موتور (27 متد)
php bin/tvn test engines/view

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\View;

// API عمومی و مثال‌ها: docs/03-engines/view.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/view/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── benchmarks/      # بنچمارک (اهداف DOC-112)
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/view.md`](../../docs/03-engines/view.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
