# کیت ERP — `tavaan.kit.erp` 🏭

> برنامه‌ریزی منابع سازمانی یکپارچه با دفتر کل مرکزی — DOC-097 / TVN-0097.

## نصب

```bash
php bin/tvn kit:install erp --with-demo
```

گام‌ها: پیش‌نیازها → فعال‌سازی ماژول‌ها (core-user + erp) → preset
(standard پیش‌فرض) → migrate (۲۴ جدول `erp_*`) → پوسته → RBAC (۲۸ مجوز،
۱۰ نقش) → seed دمو (chart of accounts استاندارد، ۲ انبار، ۶ کالا،
تأمین‌کننده/مشتری، BOM، ۳ پرسنل + کارکرد ماه).

## Presetها

| Preset | ویژگی‌ها |
|---|---|
| `minimal` | فقط essentials — بدون اعلان |
| `standard` | پیش‌فرض DOC-097: آستانه‌های ۱۰۰M/۵۰۰M ریال، auto-post اسناد سیستمی، inline-relay |
| `enterprise` | اسناد سیستمی draft (تأیید دستی)، آستانه‌های سخت‌گیرانه‌تر، مالیات حقوق ۱۰٪، relay زمان‌بندی‌شده |

## نقش‌ها (DOC-097 §6)

`accountant` • `finance-manager` (بستن دوره + تأیید مالی) • `warehouse-keeper`
• `procurement-officer` • `sales-officer` • `hr-officer` • `production-planner`
• `auditor` (فقط خواندن + audit log) • `erp-admin` • `admin`

## کاربران دمو

| کاربر | نقش ERP | رمز |
|---|---|---|
| `admin@blog.test` | erp-admin | `password` |
| `editor@blog.test` | finance-manager + accountant | `password` |
| `author@blog.test` | procurement-officer + sales-officer | `password` |
| `reader@blog.test` | auditor (فقط خواندن) | `password` |

## نقاط ورود

- پنل: `/admin/erp` (داشبورد، اسناد، حساب‌ها، دوره‌ها، انبار، خرید، فروش، تولید، حقوق، گزارش‌ها، audit)
- گزارش‌های CSV: `/admin/erp/reports/*.csv`
- راستی‌آزمایی زنجیرهٔ audit: `php bin/tvn audit:verify`
- رلهٔ Outbox (production): `php bin/tvn event:outbox-relay`

## مستندات

- `docs/admin-guide.md` — راهنمای مدیر (جریان‌های خرید/فروش/تولید/حقوق/بستن دوره)
- `docs/user-guide.md` — راهنمای کاربر (هر نقش چه می‌بیند/چه می‌کند)
- `modules/erp/README.md` — معماری ماژول
- `docs/05-applications/erp.md` — سند مرجع (DOC-097)
