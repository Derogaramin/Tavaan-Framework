# تیکت‌های `engines/i18n`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.i18n` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\I18n\` |
| آمار واقعی | 7 فایل / 1,083 خط در src • 2 فایل تست / 25 متد تست |

---

## TVN-0025 — i18n

| فیلد | مقدار |
|---|---|
| فاز | M2 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-069](../../docs/03-engines/i18n.md) |
| پیش‌نیاز | TVN-0003 (Config Repository + ENV Loader) |
| برآورد LOC | ~900 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/i18n` (25 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

