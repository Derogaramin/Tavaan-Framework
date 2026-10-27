# تیکت‌های `engines/http`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.http` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Http\` |
| آمار واقعی | 47 فایل / 6,351 خط در src • 4 فایل تست / 70 متد تست |

---

## TVN-0020 — HTTP Request/Response/Kernel

| فیلد | مقدار |
|---|---|
| فاز | M2 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-039](../../docs/03-engines/http.md) |
| پیش‌نیاز | TVN-0005 (Pipeline)، TVN-0006 (Error/Exception Handler + LogMasker) |
| برآورد LOC | ~2,800 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/http` (70 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

