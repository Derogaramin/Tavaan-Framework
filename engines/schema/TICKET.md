# تیکت‌های `engines/schema`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.schema` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Database\Schema\` |
| آمار واقعی | 2 فایل / 389 خط در src • 2 فایل تست / 17 متد تست |

---

## TVN-0031 — Schema Builder + Blueprint

| فیلد | مقدار |
|---|---|
| فاز | M3 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-048](../../docs/03-engines/schema.md) |
| پیش‌نیاز | TVN-0030 (Database + QueryBuilder + Grammar) |
| برآورد LOC | ~1,100 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/schema` (17 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

