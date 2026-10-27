# موتور گزارش‌ساز — `Report`

> M7 — Dataset (Closure/QueryBuilder/SQL/آرایه) + فیلترهای allowlist، Group/Subtotal، Pivot، Aggregate با Decimal (بدون float — ADR-0013)، تاریخ جلالی، خروجی CSV (BOM+delimiter «;»+streaming+🔒 ضد CSV Injection)، xlsx خودنوشته OOXML با ZipWriterPure (بدون PhpSpreadsheet/ext-zip)، HTML با قالب report::table + fallback escaped، JSON، PDF اختیاری، 🔒 sandbox مسیر export و max_rows.
>
> مستند کامل: [REPORT](../../docs/03-engines/report.md) • تیکت: TVN-0072 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.report` |
| namespace | `Tavaan\Engine\Report\` |
| Facade | `Tavaan\Core\Facade\Facades\Report` (accessor: `report`) |
| پیکربندی | `config/report.php` → `config('report.*')` |
| Provider | `Tavaan\Engine\Report\ReportServiceProvider` |
| وابستگی سخت | `config` |
| وابستگی نرم | `database`، `filesystem`، `view`، `i18n`، `compat`، `http` |
| آمار | 20 فایل / ~2,416 خط در src • 1 فایل تست (23 متد) |

## شروع سریع

```bash
# تست‌های موتور (23 متد)
php bin/tvn test engines/report

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Report;

// API عمومی و مثال‌ها: docs/03-engines/report.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/report/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── benchmarks/      # بنچمارک (اهداف DOC-112)
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/report.md`](../../docs/03-engines/report.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
