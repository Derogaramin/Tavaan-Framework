# تیکت‌های `kits/erp`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.kit.erp` |
| نسخه | 1.0.0 |
| ماژول‌ها | `tavaan.core-user`، `tavaan.erp` |
| نقش‌ها | 10 (accountant، finance-manager، warehouse-keeper، procurement-officer، sales-officer، hr-officer، production-planner، auditor، erp-admin، admin) |

---

## TVN-0097 — Kit: ERP

| فیلد | مقدار |
|---|---|
| فاز | M9 |
| نوع/اولویت | kit |
| وضعیت | ✅ done |
| سند | [DOC-097](../../docs/05-applications/erp.md) |

### شواهد پیاده‌سازی
- نصب: `php bin/tvn kit:install erp --with-demo`
- گزارش پیاده‌سازی: [M9-erp-implementation-report.md](../../docs/reports/M9-erp-implementation-report.md)
- راهنمای کیت: [README.md](README.md)
- ماژول: `modules/erp` ([تیکت](../../modules/erp/TICKET.md))

