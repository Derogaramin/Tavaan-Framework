# تیکت‌های `engines/cookie`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.cookie` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Http\Cookie\` |
| آمار واقعی | 1 فایل / 12 خط در src • 0 فایل تست / 0 متد تست |

---

## TVN-0023 — Cookie رمزنگاری‌شده

| فیلد | مقدار |
|---|---|
| فاز | M2 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-042](../../docs/03-engines/cookie.md) |
| پیش‌نیاز | TVN-0020 (HTTP Request/Response/Kernel)، TVN-0054 (Crypt (AES-GCM + HKDF)) |
| برآورد LOC | ~400 |

### شواهد پیاده‌سازی
- تست محلی ندارد — پوشش غیرمستقیم از راه تست‌های سراسری/موتورهای همسایه: `php bin/tvn test`
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

