# تیکت‌های `engines/view`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.view` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\View\` |
| آمار واقعی | 12 فایل / 1,679 خط در src • 1 فایل تست / 27 متد تست |

---

## TVN-0024 — View Engine (.tv.php)

| فیلد | مقدار |
|---|---|
| فاز | M2 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-043](../../docs/03-engines/view.md) |
| پیش‌نیاز | TVN-0004 (Event Dispatcher + Outbox)، TVN-0043 |
| برآورد LOC | ~1,800 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/view` (27 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

