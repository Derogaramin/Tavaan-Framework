# تیکت‌های `engines/console`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.console` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Console\` |
| آمار واقعی | 31 فایل / 3,359 خط در src • 2 فایل تست / 22 متد تست |

---

## TVN-0011 — Console Engine (CLI framework)

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-076](../../docs/03-engines/console.md) |
| پیش‌نیاز | TVN-0002 (Container با Auto-Wiring)، TVN-0004 (Event Dispatcher + Outbox) |
| برآورد LOC | ~1,600 |

### معیارهای پذیرش
- AC-CLI-01..04

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/console` (22 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

### راهنمای پیاده‌سازی (agent_hints)
> bin/tvn entry. Signature parser. رنگی. Command discovery + cache.

