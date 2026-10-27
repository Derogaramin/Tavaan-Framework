# تیکت‌های `kits/bpms`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.kit.bpms` |
| نسخه | 1.0.0 |
| ماژول‌ها | `tavaan.core-user`، `tavaan.bpms` |
| نقش‌ها | 7 (process-designer، process-admin، task-assignee، monitor، auditor، manager، admin) |

---

## TVN-0098 — Kit: BPMS

| فیلد | مقدار |
|---|---|
| فاز | M9 |
| نوع/اولویت | kit |
| وضعیت | ✅ done |
| سند | [DOC-098](../../docs/05-applications/bpms.md) |

### شواهد پیاده‌سازی
- نصب: `php bin/tvn kit:install bpms --with-demo`
- گزارش پیاده‌سازی: [M9-bpms-implementation-report.md](../../docs/reports/M9-bpms-implementation-report.md)
- راهنمای کیت: [README.md](README.md)
- ماژول: `modules/bpms` ([تیکت](../../modules/bpms/TICKET.md))

