# تیکت‌های `kits/crm`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.kit.crm` |
| نسخه | 1.0.0 |
| ماژول‌ها | `tavaan.core-user`، `tavaan.crm`، `tavaan.shop` |
| نقش‌ها | 7 (sales-agent، sales-manager، support-agent، support-manager، marketing، crm-admin، admin) |

---

## TVN-0096 — Kit: CRM

| فیلد | مقدار |
|---|---|
| فاز | M9 |
| نوع/اولویت | kit |
| وضعیت | ✅ done |
| سند | [DOC-096](../../docs/05-applications/crm.md) |

### شواهد پیاده‌سازی
- نصب: `php bin/tvn kit:install crm --with-demo`
- گزارش پیاده‌سازی: [M9-crm-implementation-report.md](../../docs/reports/M9-crm-implementation-report.md)
- راهنمای کیت: [README.md](README.md)
- ماژول: `modules/crm` ([تیکت](../../modules/crm/TICKET.md))

