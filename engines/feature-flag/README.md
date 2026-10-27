# موتور پرچم قابلیت — `FeatureFlag`

> M6 — Targeting با قواعد role/permission/user_id/tenant/percentage/env/ip، Kill Switch فوری (cache)، درصد پایدار (crc32(seed|feature))، A/B Testing پایدار، درایورهای config/database با کش نسخه‌دار، Feature::for($user) scoped.
>
> مستند کامل: [FEATURE-FLAG](../../docs/03-engines/feature-flag.md) • تیکت: TVN-0075 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.feature-flag` |
| namespace | `Tavaan\Engine\FeatureFlag\` |
| Facade | `Tavaan\Core\Facade\Facades\Feature` (accessor: `feature`) |
| پیکربندی | `config/features.php` → `config('features.*')` |
| Provider | `Tavaan\Engine\FeatureFlag\FeatureFlagServiceProvider` |
| وابستگی سخت | `config` |
| وابستگی نرم | `cache`، `database`، `rbac`، `tenancy` |
| آمار | 5 فایل / ~667 خط در src • 1 فایل تست (22 متد) |

## شروع سریع

```bash
# تست‌های موتور (22 متد)
php bin/tvn test engines/feature-flag

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Feature;

// API عمومی و مثال‌ها: docs/03-engines/feature-flag.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/feature-flag/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/feature-flag.md`](../../docs/03-engines/feature-flag.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
