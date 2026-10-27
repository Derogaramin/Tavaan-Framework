# تیکت‌های `engines/orm`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.orm` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Database\ORM\` |
| آمار واقعی | 5 فایل / 937 خط در src • 3 فایل تست / 34 متد تست |

---

## TVN-0033 — ORM (ActiveRecord + Repository)

| فیلد | مقدار |
|---|---|
| فاز | M3 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-050](../../docs/03-engines/orm.md) |
| پیش‌نیاز | TVN-0030 (Database + QueryBuilder + Grammar)، TVN-0004 (Event Dispatcher + Outbox) |
| برآورد LOC | ~4,000 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/orm` (34 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

