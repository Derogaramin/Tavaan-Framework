# موتور ممیزی ضدتقلب — `Audit`

> M4 — زنجیرهٔ هش HMAC (tamper-evident)، Diff فیلدها، کاربر/IP/trace، Observer خودکار مدل‌ها، exclude_fields.
>
> مستند کامل: [AUDIT](../../docs/03-engines/audit.md) • تیکت: TVN-0058 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.audit` |
| namespace | `Tavaan\Engine\Audit\` |
| Facade | `Tavaan\Core\Facade\Facades\Audit` (accessor: `audit`) |
| پیکربندی | `config/audit.php` → `config('audit.*')` |
| Provider | `Tavaan\Engine\Audit\AuditServiceProvider` |
| وابستگی سخت | `event`، `database`، `log`، `crypt`، `config` |
| وابستگی نرم | `orm`، `auth`، `http` |
| جداول | `tvn_audit_logs` |
| فرمان‌های CLI | `tvn audit:verify`، `tvn audit:export`، `tvn audit:prune`، `tvn audit:show` |
| آمار | 10 فایل / ~809 خط در src • 1 فایل تست (9 متد) |

## شروع سریع

```bash
# تست‌های موتور (9 متد)
php bin/tvn test engines/audit

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Audit;

// API عمومی و مثال‌ها: docs/03-engines/audit.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/audit/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/audit.md`](../../docs/03-engines/audit.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
