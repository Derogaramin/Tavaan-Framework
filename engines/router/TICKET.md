# تیکت‌های `engines/router`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.router` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Http\Routing\` |
| آمار واقعی | 1 فایل / 42 خط در src • 0 فایل تست / 0 متد تست |

---

## TVN-0021 — Router با Compiled Trie

| فیلد | مقدار |
|---|---|
| فاز | M2 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-040](../../docs/03-engines/router.md) |
| پیش‌نیاز | TVN-0020 (HTTP Request/Response/Kernel) |
| برآورد LOC | ~1,400 |

### شواهد پیاده‌سازی
- تست محلی ندارد — پوشش غیرمستقیم از راه تست‌های سراسری/موتورهای همسایه: `php bin/tvn test`
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

