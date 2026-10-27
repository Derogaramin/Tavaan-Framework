# تیکت‌های `engines/cache`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.cache` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Cache\` |
| آمار واقعی | 13 فایل / 1,977 خط در src • 1 فایل تست / 20 متد تست |

---

## TVN-0060 — Cache (۶ درایور)

| فیلد | مقدار |
|---|---|
| فاز | M4 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-060](../../docs/03-engines/cache.md) |
| پیش‌نیاز | TVN-0003 (Config Repository + ENV Loader)، TVN-0004 (Event Dispatcher + Outbox) |
| برآورد LOC | ~1,400 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/cache` (20 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

