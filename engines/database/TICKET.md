# تیکت‌های `engines/database`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.database` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Database\` |
| آمار واقعی | 82 فایل / 11,916 خط در src • 3 فایل تست / 43 متد تست |

---

## TVN-0030 — Database + QueryBuilder + Grammar

| فیلد | مقدار |
|---|---|
| فاز | M3 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-047](../../docs/03-engines/database.md) |
| پیش‌نیاز | TVN-0003 (Config Repository + ENV Loader)، TVN-0004 (Event Dispatcher + Outbox)، TVN-0007 (Log Engine (چندکاناله + Mask)) |
| برآورد LOC | ~3,200 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/database` (43 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

---

## TVN-0034 — Pagination

| فیلد | مقدار |
|---|---|
| فاز | M3 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-053](../../docs/03-engines/pagination.md) |
| پیش‌نیاز | TVN-0030 (Database + QueryBuilder + Grammar) |
| برآورد LOC | ~300 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/database` (43 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

