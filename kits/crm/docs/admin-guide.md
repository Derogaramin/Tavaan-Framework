# راهنمای تیم — پنل CRM

## نقش‌ها (DOC-096 §4)

| نقش | توانایی |
|---|---|
| sales-agent | سرنخ/فرصت **خود** (policy مالکیت) + فعالیت + مشاهدهٔ مخاطبان |
| sales-manager | همهٔ سرنخ‌ها/فرصت‌ها + assign + convert + move + گزارش |
| support-agent | تیکت‌های **assigned خود** (پاسخ/حل) |
| support-manager | همهٔ تیکت‌ها + assign + اولویت + گزارش + دریافت escalation |
| marketing | کمپین‌ها + مخاطبان |
| crm-admin / admin | همهٔ `crm.*` |

## مخاطبان — `/admin/crm/contacts`

- ساخت/ویرایش: نام، شرکت، ایمیل (یکتا)، موبایل، **کد ملی** (رمزنگاری‌شده
  در rest با کلید app — در ویو mask: `******5678`)، منبع، برچسب‌ها.
- پروندهٔ مخاطب: سرنخ‌ها/فرصت‌ها/تیکت‌ها/فعالیت‌ها یک‌جا + تخصیص مسئول.
- حذف = soft (deleted_at) — دادهٔ تاریخی گزارش‌ها حفظ می‌شود.

## سرنخ‌ها — `/admin/crm/leads`

- **Lead Scoring خودکار** (DOC-096 §3.2): منبع (referral ۲۰/غیره ۵) + شرکت
  (۱۵) + ارزش (>۱۰۰M → ۲۵/غیره ۱۰) + تعامل (هر فعالیت ۵، سقف ۱۵) — cap ۱۰۰.
  امتیاز در ساخت/ویرایش/ثبت فعالیت بازشماری می‌شود.
- جریان: `جدید → تأیید صلاحیت → تبدیل به فرصت` (یا ازدست‌رفته با دلیل).
- تبدیل: Opportunity در مرحلهٔ «احتمال اولیه» + **نمونهٔ Workflow**
  `crm-sales-pipeline` ساخته می‌شود.
- `tvn crm:recalculate-scores` — بازشماری دسته‌ای پس از تغییر وزن‌ها/import.

## Pipeline — `/admin/crm/opportunities`

- مرحله‌ها: احتمال اولیه(۱۰٪) → واجد صلاحیت(۲۵٪) → پیشنهاد(۵۰٪) →
  مذاکره(۷۵٪) → موفق(۱۰۰٪)/ناموفق(۰٪). احتمال‌ها از
  `config('crm.pipeline.probabilities')`.
- جابجایی: رو به جلو (هر پرش) یا یک گام عقب؛ بستن با **دلیل الزامی** برای
  ناموفق. هر جابجایی = گذار در Workflow (تاریخچه/visualizer موتور) + رویداد
  `crm.opportunity.stage_changed`.
- **Forecast وزنی** = Σ ارزش×احتمال — بالای فهرست و در گزارش‌ها (AC-CRM-07).

## تیکت‌ها — `/admin/crm/tickets`

- **SLA** بر اساس اولویت: فوری ۲h / زیاد ۴h / عادی ۸h / کم ۲۴h (config).
- جریان: open → پاسخ agent = **pending** (توقف SLA) → پاسخ مشتری = open →
  resolve → close. تغییر اولویت = بازنشانی SLA.
- **Escalation خودکار** (AC-CRM-08): `crm:check-sla` هر دقیقه (schedule) —
  تیکت openِ گذشته از سررسید → `escalated_at` + رویداد
  `crm.ticket.sla_breached` + اعلان به نقش‌های `crm.sla.escalate_roles`.
  Idempotent — هر تیکت یک‌بار escalate می‌شود.
- **یادداشت داخلی**: پاسخ با تیک «داخلی» — وضعیت عوض نمی‌شود و مشتری
  هرگز آن را نمی‌بیند.
- auto-close: resolvedهای بیش از N روز → closed (config: tickets.auto_close_days).

## کمپین‌ها — `/admin/crm/campaigns`

- نوع: email (Mail engine) / sms (فعلاً log-only — بک‌لاگ gateway).
- audience_query JSON: `{"tags":["vip"],"source":"shop","status":"active","owner_id":12}`
  — tags = AND؛ مخاطبان blocked/حذف‌شده هرگز انتخاب نمی‌شوند.
- personalize: `{first_name}` `{last_name}` `{company}`.
- ارسال: دستی (دکمه) یا زمان‌بندی‌شده (`crm:send-campaigns` روی schedule).
  **Idempotent** — اجرای دوبارهٔ cron پیام تکراری نمی‌فرستد (uk گیرنده).

## گزارش‌ها — `/admin/crm/reports`

هفت گزارش DOC-096 §6: pipeline بر اساس مرحله • win/loss • forecast وزنی •
کارکرد کارشناسان (فروش+پشتیبانی) • زمان حل/انطباق SLA • اثربخشی منبع سرنخ •
درآمد ماهانه. خروجی CSV با BOM و ضد CSV Injection.

## یکپارچگی فروشگاه (E2E-06)

با نصب کیت shop، رویداد `shop.order.paid` به‌طور خودکار مخاطب می‌سازد/
به‌روزرسانی می‌کند (source=shop + آخرین سفارش/مبلغ در custom_fields). بدون
shop هم از راه API قابل تغذیه است.

## نگهداری

```bash
tvn crm:check-sla             # SLA + escalation + auto-close
tvn crm:send-campaigns        # کمپین‌های زمان‌بندی‌شده
tvn crm:recalculate-scores    # بازشماری امتیاز سرنخ‌ها
```
