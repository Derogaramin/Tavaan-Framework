# تیکت‌های `engines/jalali`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.jalali` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\I18n\Jalali\` |
| آمار واقعی | 0 فایل / 0 خط در src • 0 فایل تست / 0 متد تست |

---

## TVN-0026 — Jalali Calendar

| فیلد | مقدار |
|---|---|
| فاز | M2 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-070](../../docs/03-engines/jalali.md) |
| پیش‌نیاز | TVN-0025 (i18n) |
| برآورد LOC | ~800 |

### شواهد پیاده‌سازی
- تست محلی ندارد — پوشش غیرمستقیم از راه تست‌های سراسری/موتورهای همسایه: `php bin/tvn test`
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

