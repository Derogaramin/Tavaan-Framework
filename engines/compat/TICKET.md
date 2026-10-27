# تیکت‌های `engines/compat`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.compat` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Compat\` |
| آمار واقعی | 7 فایل / 890 خط در src • 1 فایل تست / 12 متد تست |

---

## TVN-0008 — Compat/Polyfill Engine

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-083E](../../docs/03-engines/compat.md) |
| برآورد LOC | ~400 |

### معیارهای پذیرش
- AC-CMP-01..05

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/compat` (12 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

### راهنمای پیاده‌سازی (agent_hints)
> Decimal بدون bcmath. ZipWriterPure. MagicBytes. StreamHttpTransport. Utf8.

