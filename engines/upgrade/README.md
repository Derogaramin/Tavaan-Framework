# موتور ارتقای نسخهٔ سیستم — `Upgrade`

> M8/TVN-0081: جریان ارتقا (pre-check → backup اجباری → maintenance → path گام‌به‌گام → schema+data patch+config migration → verify → up → rollback خودکار در شکست).
>
> مستند کامل: [UPGRADE](../../docs/03-engines/upgrade.md) • تیکت: TVN-0081 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.upgrade` |
| namespace | `Tavaan\Engine\Upgrade\` |
| Provider | `Tavaan\Engine\Upgrade\UpgradeServiceProvider` |
| وابستگی سخت | `console`، `database`، `filesystem` |
| جداول | `tvn_upgrades`، `tvn_upgrade_meta` |
| فرمان‌های CLI | `tvn upgrade`، `tvn upgrade:check`، `tvn upgrade:rollback`، `tvn upgrade:status`، `tvn upgrade:patches` |
| آمار | 16 فایل / ~1,481 خط در src • 2 فایل تست (24 متد) |

## شروع سریع

```bash
# تست‌های موتور (24 متد)
php bin/tvn test engines/upgrade

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/upgrade/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/upgrade.md`](../../docs/03-engines/upgrade.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
