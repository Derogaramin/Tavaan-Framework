# موتور حافظهٔ نهان — `Cache`

> M4 — ۶ درایور (array/file/database/redis/apcu/memcached) با fallback خودکار به file. Tag + Lock اتمیک + Stampede Protection. 🔒 سریال‌سازی فقط JSON.
>
> مستند کامل: [CACHE](../../docs/03-engines/cache.md) • تیکت: TVN-0060 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.cache` |
| namespace | `Tavaan\Engine\Cache\` |
| Facade | `Tavaan\Core\Facade\Facades\Cache` (accessor: `cache`) |
| پیکربندی | `config/cache.php` → `config('cache.*')` |
| Provider | `Tavaan\Engine\Cache\CacheServiceProvider` |
| وابستگی سخت | `config`، `event` |
| وابستگی نرم | `database` |
| جداول | `cache`، `cache_locks` |
| فرمان‌های CLI | `tvn cache:clear` |
| آمار | 13 فایل / ~1,977 خط در src • 1 فایل تست (20 متد) |

## شروع سریع

```bash
# تست‌های موتور (20 متد)
php bin/tvn test engines/cache

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Cache;

// API عمومی و مثال‌ها: docs/03-engines/cache.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/cache/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/cache.md`](../../docs/03-engines/cache.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
