# کیت مدیریت ارتباط با مشتری (CRM) — `kits/crm` (DOC-096 + DOC-090)

نصب کامل CRM تَوان با یک دستور:

```bash
php bin/tvn kit:install crm --preset=standard --with-demo
```

## آنچه نصب می‌شود

| جزء | جزئیات |
|---|---|
| ماژول‌ها | core-user, **crm** (shop اختیاری — همگام‌سازی مشتری رویدادمحور) |
| جداول | ۸ جدول `crm_*` + جداول ماژول‌های پیش‌نیاز |
| نقش‌ها | sales-agent, sales-manager, support-agent, support-manager, marketing, crm-admin, admin |
| مجوزها | ۲۵ کلید `crm.*` (rbac:sync خودکار) |
| پوسته | `themes/default` → `resources/views/vendor/crm/` + `public/assets/kits/crm/` |
| دمو | ۵ مخاطب، ۳ سرنخ (new/qualified/lost با امتیاز)، ۴ فرصت در مرحله‌های pipeline (won/lost/باز)، فعالیت‌ها، ۳ تیکت (open با **SLA گذشته** برای دمو escalation / pending / resolved)، کمپین پیش‌نویس، انتصاب نقش تیم |

## Presetها

| Preset | SLA (عادی) | auto-close | اعلان‌ها | همگام‌سازی shop |
|---|---|---|---|---|
| `minimal` | ۸ ساعت | خاموش | حداقلی | خاموش |
| `standard` (پیش‌فرض) | ۸ ساعت | ۷ روز | کامل | فعال |
| `enterprise` | ۴ ساعت | ۳ روز | کامل | فعال |

> preset فقط overlay روی `config/crm.php` می‌نویسد (gitignored). وزن‌های
> Lead Scoring، احتمالات pipeline و ساعت‌های SLA همگی از config قابل تنظیم‌اند.

## مسیرهای کلیدی پس از نصب

- **پرتال پشتیبانی (عمومی)**: `/crm/support` (ثبت تیکت) • `/crm/support/track` • `/crm/support/{number}` (پیگیری + پاسخ)
- پنل: `/admin/crm` (داشبورد) • `/admin/crm/contacts|leads|opportunities|tickets|activities|campaigns|reports`
- گزارش CSV: `/admin/crm/reports/pipeline.csv`
- **API یکپارچگی** (DOC-096 §7): `POST /api/v1/crm/leads` و `POST /api/v1/crm/tickets` (Throttle 10/60s) + `GET /api/v1/crm/tickets/{number}`
- Cron: `crm:check-sla` (هر دقیقه — escalation خودکار + auto-close) و `crm:send-campaigns` (کمپین‌های زمان‌بندی‌شده) — خودکار روی schedule ثبت می‌شوند

## کاربران دمو (`--with-demo`)

| ایمیل | رمز | نقش‌های CRM |
|---|---|---|
| admin@blog.test | password | crm-admin + admin |
| author@blog.test | password | sales-manager |
| editor@blog.test | password | support-manager |
| reader@blog.test | password | sales-agent |

## راهنماها

- `docs/user-guide.md` — پرتال پشتیبانی (دید مشتری)
- `docs/admin-guide.md` — pipeline/سرنخ/تیکت/کمپین/گزارش (دید تیم)
