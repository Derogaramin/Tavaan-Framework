# تیکت‌های `engines/cors`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.cors` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Http\Cors\` |
| آمار واقعی | 1 فایل / 18 خط در src • 0 فایل تست / 0 متد تست |

---

## TVN-0027 — CORS

| فیلد | مقدار |
|---|---|
| فاز | M2 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-046](../../docs/03-engines/cors.md) |
| پیش‌نیاز | TVN-0020 (HTTP Request/Response/Kernel) |
| برآورد LOC | ~350 |

### شواهد پیاده‌سازی
- تست محلی ندارد — پوشش غیرمستقیم از راه تست‌های سراسری/موتورهای همسایه: `php bin/tvn test`
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

