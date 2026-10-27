# موتور چنداجاره‌ای — `Tenancy`

> M6 — سه استراتژی (Shared/Row-Level با TenantScope+Observer، DB-per-Tenant با اتصال پویا و provisioning، Schema-per-Tenant با search_path)، زنجیرهٔ Resolver (Domain/Header/Path/Query/Token/Default)، TenantContext با run/withoutCurrentScope (مجوز+Audit)، QuotaGuard پنجره‌دار، cache ایزوله (TenantScopeStore — driver «tenant»)، TenantConfigRepository overlay، migration جدول tvn_tenants.
>
> مستند کامل: [TENANCY](../../docs/03-engines/tenancy.md) • تیکت: TVN-0082 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.tenancy` |
| namespace | `Tavaan\Engine\Tenancy\` |
| Facade | `Tavaan\Core\Facade\Facades\Tenant` (accessor: `tenant`) |
| پیکربندی | `config/tenancy.php` → `config('tenancy.*')` |
| Provider | `Tavaan\Engine\Tenancy\TenancyServiceProvider` |
| وابستگی سخت | `config` |
| وابستگی نرم | `database`، `cache`، `http`، `event`، `console`، `migration`، `orm`، `crypt` |
| فرمان‌های CLI | `tvn tenant:create`، `tvn tenant:list`، `tvn tenant:migrate`، `tvn tenant:delete`، `tvn tenant:stats`، `tvn db:tenant-guard` |
| آمار | 40 فایل / ~3,319 خط در src • 1 فایل تست (35 متد) |

## شروع سریع

```bash
# تست‌های موتور (35 متد)
php bin/tvn test engines/tenancy

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Tenant;

// API عمومی و مثال‌ها: docs/03-engines/tenancy.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/tenancy/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/tenancy.md`](../../docs/03-engines/tenancy.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
