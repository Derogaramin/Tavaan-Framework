# تیکت‌های `kits/news`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.kit.news` |
| نسخه | 1.0.0 |
| ماژول‌ها | `tavaan.core-user`، `tavaan.content`، `tavaan.comment`، `tavaan.media`، `tavaan.news`، `tavaan.blog` |
| نقش‌ها | 5 (reader، reporter، editor، chief-editor، admin) |

---

## TVN-0092 — Kit: News Portal

| فیلد | مقدار |
|---|---|
| فاز | M9 |
| نوع/اولویت | kit |
| وضعیت | ✅ done |
| سند | [DOC-092](../../docs/05-applications/news-portal.md) |

### شواهد پیاده‌سازی
- نصب: `php bin/tvn kit:install news --with-demo`
- گزارش پیاده‌سازی: [M9-news-implementation-report.md](../../docs/reports/M9-news-implementation-report.md)
- راهنمای کیت: [README.md](README.md)
- ماژول: `modules/news` ([تیکت](../../modules/news/TICKET.md))

