# موتور مسیریاب Compiled Trie — `Router`

> کد در engines/http/src/Routing (namespace مطابق DOC-040 machine block).
>
> مستند کامل: [ROUTER](../../docs/03-engines/router.md) • تیکت: TVN-0021 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.router` |
| namespace | `Tavaan\Engine\Http\Routing\` |
| Facade | `Tavaan\Core\Facade\Facades\Route` (accessor: `router`) |
| پیکربندی | `config/router.php` → `config('router.*')` |
| وابستگی سخت | `http`، `container`، `config` |
| فرمان‌های CLI | `tvn route:list`، `tvn route:cache`، `tvn route:clear`، `tvn route:match` |
| آمار | 0 فایل / ~0 خط در src • 0 فایل تست (0 متد) |

## شروع سریع

```bash
# این موتور تست محلی ندارد — پوشش غیرمستقیم از راه سوئیت سراسری
# و تست موتورهای همسایه (مثل router در engines/http/tests)
php bin/tvn test

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Route;

// API عمومی و مثال‌ها: docs/03-engines/router.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/router/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/router.md`](../../docs/03-engines/router.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
