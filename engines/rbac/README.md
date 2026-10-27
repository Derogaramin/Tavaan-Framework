# موتور نقش و کنترل دسترسی — `RBAC`

> M4 — RBAC+ABAC با Gate/Policy، سلسله‌مراتب نقش، wildcard، delegation موقت، Row-Level Scope، sync از manifest ماژول‌ها.
>
> مستند کامل: [RBAC](../../docs/03-engines/rbac.md) • تیکت: TVN-0057 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.rbac` |
| namespace | `Tavaan\Engine\Rbac\` |
| Facade | `Tavaan\Core\Facade\Facades\Gate` (accessor: `gate`) |
| پیکربندی | `config/rbac.php` → `config('rbac.*')` |
| Provider | `Tavaan\Engine\Rbac\RbacServiceProvider` |
| وابستگی سخت | `auth`، `database`، `cache`، `event`، `config` |
| جداول | `tvn_permissions`، `tvn_roles`، `tvn_role_user`، `tvn_permission_role`، `tvn_delegations` |
| فرمان‌های CLI | `tvn rbac:sync` |
| آمار | 12 فایل / ~1,514 خط در src • 1 فایل تست (12 متد) |

## شروع سریع

```bash
# تست‌های موتور (12 متد)
php bin/tvn test engines/rbac

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Gate;

// API عمومی و مثال‌ها: docs/03-engines/rbac.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/rbac/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/rbac.md`](../../docs/03-engines/rbac.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
