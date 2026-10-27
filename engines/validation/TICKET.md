# تیکت‌های `engines/validation`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.validation` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Validation\` |
| آمار واقعی | 25 فایل / 3,104 خط در src • 3 فایل تست / 38 متد تست |

---

## TVN-0035 — Validation (۸۵ قاعده)

| فیلد | مقدار |
|---|---|
| فاز | M3 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-051](../../docs/03-engines/validation.md) |
| پیش‌نیاز | TVN-0025 (i18n)، TVN-0030 (Database + QueryBuilder + Grammar) |
| برآورد LOC | ~2,200 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/validation` (38 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

