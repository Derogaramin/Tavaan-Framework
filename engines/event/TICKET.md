# تیکت‌های `engines/event`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.event` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Event\` |
| آمار واقعی | 12 فایل / 1,261 خط در src • 2 فایل تست / 21 متد تست |

---

## TVN-0004 — Event Dispatcher + Outbox

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-033](../../docs/03-engines/event.md) • [DOC-023](../../docs/02-architecture/event-driven.md) |
| پیش‌نیاز | TVN-0002 (Container با Auto-Wiring)، TVN-0003 (Config Repository + ENV Loader) |
| برآورد LOC | ~900 |

### معیارهای پذیرش
- AC-EVT-01..13

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/event` (21 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

### راهنمای پیاده‌سازی (agent_hints)
> Wildcard Trie. Priority قطعی. Outbox در تراکنش + Relay با SKIP LOCKED.

