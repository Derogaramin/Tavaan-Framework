# ماژول CRM (tavaan.crm) 🤝

مدیریت ارتباط با مشتری — پیاده‌سازی **DOC-096** (تیکت TVN-0096، فاز M9).

> معماری: **hexagonal** (پورت/آداپتر) — الگوی ماژول‌های `shop`/`lms`.
> مرز ماژول (ARCH-003): فقط Core Contracts/Facades/Support + Contracts ماژول
> هم‌خانواده (`core-user`) + SDK مجاز (`Console\Command`). یکپارچگی با shop
> **فقط از راه رویداد رشته‌ای** `shop.order.paid` (بدون ارجاع کلاسی).

## دامنه (DOC-096 §1)

```
Lead & Contact │ Sales Pipeline │ Activity │ Support (Ticketing) │ Campaign │ Reporting
```

| Aggregate | Root | پیاده‌سازی |
|---|---|---|
| Contact | `Contact` | + tags/custom_fields + national_id **رمزنگاری‌شده در rest** (Crypt facade) |
| Lead | `Lead` | + score (LeadScorer §3.2) + converted_to |
| Opportunity | `Opportunity` | + stage/probability (pipeline §3.1) + workflow_instance_id |
| Activity | `Activity` | پلی‌مورف: contact/lead/opportunity/ticket • task با due |
| Ticket | `Ticket` | + TicketReply (agent/customer/internal) + SLA + escalation |
| Campaign | `Campaign` | + recipients (idempotent) + audience_query |

## ساختار

```
modules/crm/
├── module.json                  ← manifest (events/permissions/navigation/tables)
├── config/crm.php               ← pipeline/scoring/sla/tickets/campaigns/encryption/sync
├── config/rbac.php              ← policies اعلامی (Lead/Opportunity/Ticket/Contact)
├── migrations/…create_crm_tables.php   ← ۸ جدول crm_*
├── routes/{web,admin,api}.php   ← پرتال پشتیبانی + ۳۴ روت پنل + ۳ روت API
├── seeds/demo.php               ← دادهٔ دمو (idempotent)
├── src/
│   ├── Domain/Entities/…        ← Contact/Lead/Opportunity/Activity/Ticket/TicketReply/Campaign
│   ├── Domain/Enums/…           ← ۷ enum (ماتریس انتقال Lead/Ticket/Campaign/Stage)
│   ├── Domain/Events/…          ← ۱۰ رویداد (DTO تایپ‌دار + eventName/toPayload)
│   ├── Domain/Repositories/…    ← ۶ پورت مخزن
│   ├── Domain/Services/…        ← LeadScorer/SlaPolicy/TicketNumberGenerator/ForecastCalculator/AudienceResolver (خالص)
│   ├── Application/UseCases/…   ← Contact/Lead/Pipeline/Activity/Ticket/SlaMonitor/Campaign/CrmReport
│   ├── Infrastructure/
│   │   ├── Persistence/…        ← ۶ آداپتر DB (Contact با encrypt/decrypt کدملی)
│   │   ├── Notifications/CrmNotifier.php       ← binding `notification.message` + Mail مستقیم برای مشتریِ بدون حساب
│   │   ├── Search/ContactSearchIndexer.php     ← ایندکس `crm_contacts`
│   │   ├── Workflow/PipelineWorkflow.php       ← `crm-sales-pipeline` (Workflow facade)
│   │   └── Integration/ShopContactSync.php     ← shop.order.paid → upsert مخاطب
│   ├── Http/Controllers/…       ← پرتال پشتیبانی + ۷ ادمین + API
│   ├── Policies/…               ← Lead/Opportunity/Ticket/Contact (مالکیت + «همه»)
│   ├── Console/Commands/…       ← crm:check-sla • crm:send-campaigns • crm:recalculate-scores
│   └── Providers/CrmServiceProvider.php
├── views/…                      ← RTL (پرتال + پنل: داشبورد/مخاطب/سرنخ/pipeline/تیکت/کمپین/گزارش)
└── tests/{Unit,Feature}/…
```

## جریان‌های کلیدی (DOC-096 §3)

1. **Sales Pipeline (§3.1 + AC-CRM-07)** — Lead(new) → scoring خودکار →
   qualify → convert → Opportunity(prospecting) → qualification → proposal →
   negotiation → closed_won/closed_lost. هر فرصت یک نمونهٔ Workflow
   `crm-sales-pipeline` می‌گیرد (تاریخچه/visualization موتور).
   **Forecast وزنی** = Σ value×probability٪ (int — بدون float).
2. **Lead Scoring (§3.2)** — الگوریتم مستند: referral ۲۰/غیره ۵ + شرکت ۱۵ +
   ارزش>۱۰۰M ۲۵/غیره ۱۰ + engagement (هر فعالیت ۵، سقف ۱۵)، cap=۱۰۰ —
   وزن‌ها از config. محاسبه در ساخت/ویرایش/ثبت فعالیت + `crm:recalculate-scores`.
3. **تیکت با SLA (§3.3 + AC-CRM-08)** — create → `sla_due_at` بر اساس
   priority (urgent 2h/high 4h/normal 8h/low 24h — config) → assign → پاسخ
   agent = pending (توقف SLA) → پاسخ مشتری = open → resolve → close.
   **نقض SLA → escalation خودکار** (`crm:check-sla` هر دقیقه روی schedule +
   رویداد `crm.ticket.sla_breached` + اعلان به نقش‌های config) — idempotent.
4. **کمپین (§E2E-04)** — audience_query JSON (tags/source/status/owner) →
   AudienceResolver → `Mail::raw` personalize‌شده → رکورد recipient
   (uk = idempotent در cron) → `crm.campaign.sent`. SMS فعلاً log-only.
5. **همگام‌سازی shop (§7 — E2E-06)** — listener رویداد `shop.order.paid` →
   snapshot کاربر (core-user) → upsert مخاطب (source=shop، custom_fields:
   آخرین سفارش/مجموع) + رویداد `crm.contact.synced`.

## نقش‌ها (DOC-096 §4)

| نقش | مجوزها |
|---|---|
| sales-agent | سرنخ/فرصت **خود** (policy مالکیت) + فعالیت |
| sales-manager | تیم (assign) + گزارش + «همه» |
| support-agent | تیکت‌های assigned (reply/resolve) |
| support-manager | همهٔ تیکت‌ها + assign + SLA |
| marketing | کمپین‌ها + مخاطبان |
| crm-admin | همهٔ `crm.*` |

۲۵ مجوز `crm.*` در manifest — sync با `tvn rbac:sync`.

## نکات پیاده‌سازی

- **PII**: کدملی رمزنگاری‌شده (AES-GCM با AAD=`crm.contact.national_id`) و
  در ویو mask (`••••۱۲۳۴`) — کلید نبود → ذخیره نمی‌شود (fail-safe).
- **پول**: int minor-unit (ADR-0013) — انطباق با DECIMAL مستند در گزارش.
- **XSS**: بدنهٔ تیکت/پاسخ/فعالیت = `Security::sanitize(plain)` + escape ویو.
- **API عمومی** (`/api/v1/crm/leads|tickets`): Throttle (10/60s) + validation.
- **Workflow lazy-define** — boot ماژول DB را زودهنگام مصرف نمی‌کند.

## دستورات

```bash
tvn crm:check-sla              # پایش SLA + escalation + auto-close
tvn crm:send-campaigns         # کمپین‌های زمان‌بندی‌شده
tvn crm:recalculate-scores     # بازشماری امتیاز سرنخ‌های باز
```

@see docs/05-applications/crm.md • kits/crm
