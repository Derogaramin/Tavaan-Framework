# موتور تولید کد (make:*) — `Scaffold`

> M8/TVN-0077: Stub engine خودنوشته ({{var}}/{{#if}}/{{#unless}}/{{#each}})، ۱۷ Generator، idempotent با --force + backup .bak، sandbox مسیر (path traversal → رد).
>
> مستند کامل: [SCAFFOLD](../../docs/03-engines/scaffold.md) • تیکت: TVN-0077 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.scaffold` |
| namespace | `Tavaan\Engine\Scaffold\` |
| Provider | `Tavaan\Engine\Scaffold\ScaffoldServiceProvider` |
| وابستگی سخت | `console`، `filesystem` |
| فرمان‌های CLI | `tvn make:module`، `tvn make:controller`، `tvn make:model`، `tvn make:migration`، `tvn make:request`، `tvn make:policy`، `tvn make:command`، `tvn make:job`، `tvn make:event`، `tvn make:listener`، `tvn make:seeder`، `tvn make:factory`، `tvn make:test`، `tvn make:widget`، `tvn make:resource`، `tvn make:engine`، `tvn make:middleware` |
| آمار | 40 فایل / ~2,075 خط در src • 2 فایل تست (28 متد) |

## شروع سریع

```bash
# تست‌های موتور (28 متد)
php bin/tvn test engines/scaffold

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/scaffold/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/scaffold.md`](../../docs/03-engines/scaffold.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
