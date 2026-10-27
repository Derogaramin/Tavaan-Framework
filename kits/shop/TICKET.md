# تیکت‌های `kits/shop`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.kit.shop` |
| نسخه | 1.0.0 |
| ماژول‌ها | `tavaan.core-user`، `tavaan.content`، `tavaan.comment`، `tavaan.media`، `tavaan.shop`، `tavaan.blog`، `tavaan.news` |
| نقش‌ها | 5 (customer، shop-operator، shop-manager، inventory-keeper، admin) |

---

## TVN-0093 — Kit: Shop

| فیلد | مقدار |
|---|---|
| فاز | M9 |
| نوع/اولویت | kit |
| وضعیت | ✅ done |
| سند | [DOC-093](../../docs/05-applications/shop.md) |

### شواهد پیاده‌سازی
- نصب: `php bin/tvn kit:install shop --with-demo`
- گزارش پیاده‌سازی: [M9-shop-implementation-report.md](../../docs/reports/M9-shop-implementation-report.md)
- راهنمای کیت: [README.md](README.md)
- ماژول: `modules/shop` ([تیکت](../../modules/shop/TICKET.md))

