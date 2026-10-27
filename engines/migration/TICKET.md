# تیکت‌های `engines/migration`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.migration` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Database\Migration\` |
| آمار واقعی | 6 فایل / 272 خط در src • 2 فایل تست / 13 متد تست |

---

## TVN-0032 — Migration

| فیلد | مقدار |
|---|---|
| فاز | M3 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-049](../../docs/03-engines/migration.md) |
| پیش‌نیاز | TVN-0031 (Schema Builder + Blueprint) |
| برآورد LOC | ~800 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/migration` (13 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

