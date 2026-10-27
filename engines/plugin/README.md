# موتور افزونهٔ ثالث (sandbox + امضا) — `Plugin`

> M8/TVN-0079: manifest + Ed25519 (sodium) + plugins.lock (SHA-256) + scanner توکنی + sandbox (autoload append/vendor ایزوله/capabilities) + Hook (اعلام‌شده‌محور + timeout/memory) + چرخهٔ عمر کامل.
>
> مستند کامل: [PLUGIN](../../docs/03-engines/plugin.md) • تیکت: TVN-0079 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.plugin` |
| namespace | `Tavaan\Engine\Plugin\` |
| Facade | `Tavaan\Core\Facade\Facades\Plugin` (accessor: `plugins`) |
| Provider | `Tavaan\Engine\Plugin\PluginServiceProvider` |
| وابستگی سخت | `module`، `filesystem`، `crypt` |
| فرمان‌های CLI | `tvn plugin:install`، `tvn plugin:enable`، `tvn plugin:disable`، `tvn plugin:update`، `tvn plugin:uninstall`، `tvn plugin:list`، `tvn plugin:verify`، `tvn plugin:scan` |
| آمار | 20 فایل / ~2,234 خط در src • 5 فایل تست (58 متد) |

## شروع سریع

```bash
# تست‌های موتور (58 متد)
php bin/tvn test engines/plugin

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Plugin;

// API عمومی و مثال‌ها: docs/03-engines/plugin.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/plugin/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/plugin.md`](../../docs/03-engines/plugin.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
