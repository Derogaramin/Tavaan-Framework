# گزارش پیاده‌سازی M9 — گام ۶: کیت مدیریت ارتباط با مشتری (CRM) 🤝 (TVN-0096)

| متادیتا | مقدار |
|---|---|
| تیکت | TVN-0096 — Kit: CRM |
| مستند مبنا | DOC-096 (`docs/05-applications/crm.md`) + DOC-090 (blueprint کیت) |
| نسخه | 0.14.0 |
| تاریخ | 2026-10-25 |
| نتیجه | ✅ ۲۶ تست جدید (**۱۵۳۴ کل / ۱۰۱۴۵ assertion — suite کامل سبز**) • arch:check ۰ نقض (۱۴۲۸ فایل) • security:scan ۰ بحرانی (۸ med = صورت‌مسئلهٔ پیشین، بدون یافتهٔ جدید) • lint ۱۹۸۱ فایل پاک • نصب واقعی کیت + smoke HTTP سبز (تیکت پرتال با CSRF واقعی + API intake) |

---

## ۱. دامنهٔ تحویل

پیاده‌سازی کامل DOC-096 در ماژول شش‌ضلعی `tavaan.crm` + کیت `tavaan.kit.crm`:

| محدوده (DOC-096) | پیاده‌سازی |
|---|---|
| §1.2 Aggregateها | `Contact` (tags/custom_fields/soft-delete + national_id **رمزنگاری‌شده در rest**)، `Lead` (score/converted_to)، `Opportunity` (stage/probability/workflow_instance)، `Activity` (پلی‌مورف ۴ موجودیت + task/due)، `Ticket` (+`TicketReply` با is_internal)، `Campaign` (+recipients) — ۷ entity + ۷ enum با ماتریس انتقال |
| §2 مدل داده | ۸ جدول `crm_*` — هم‌راستا با DOC + افزودنی‌های مستند: `crm_campaign_recipients` (uk = idempotency ارسال)، `tickets.escalated_at/closed_at`، `activities.lead_id`، `opportunities.workflow_instance_id`، `leads.source` (snapshot برای §6) |
| §3.1 Pipeline | Workflow `crm-sales-pipeline` (نمونه per opportunity — تاریخچه/visualizer موتور) + ماتریس stage (پرش جلو/یک گام عقب/won-lost با دلیل الزامی) + **forecast وزنی int** (Σ value×p٪) |
| §3.2 Lead Scoring | **الگوریتم عیناً از مستند**: referral ۲۰/غیره ۵ + شرکت ۱۵ + ارزش>۱۰۰M ۲۵/غیره ۱۰ + engagement (هر فعالیت ۵، سقف ۱۵) + cap ۱۰۰ — وزن‌ها config‌پذیر؛ بازشماری در ساخت/ویرایش/**ثبت فعالیت** + `crm:recalculate-scores` |
| §3.3 تیکت/SLA | number یکتا (Clock) + SLA per priority + pending=**توقف SLA** + resolve→close (reopen با پاسخ مشتری) + **escalation خودکار idempotent** + internal notes + auto-close (config) |
| §4 نقش‌ها | sales-agent/sales-manager/support-agent/support-manager/marketing/crm-admin/admin — ۲۵ مجوز + ۴ Policy مالکیت (اعلامی در config/rbac.php) |
| §5 رویدادها | ۱۰ رویداد منتشرشده (۷ مستند + assigned/lost/campaign.sent/contact.synced) + ۱ مصرفی (`shop.order.paid`) — listenerها: notifier (mail+database / Mail مستقیم برای مشتریِ بدون حساب)، search index، shop sync |
| §6 گزارش‌ها | هر ۷ گزارش مستند + CSV (BOM + ضد CSV Injection) |
| §7 یکپارچگی | API ورود lead/ticket (Throttle) + sync رویدادمحور shop (بدون ارجاع کلاسی) |

### آمار کد

| جزء | تعداد |
|---|---|
| فایل‌های PHP ماژول `modules/crm` | ۱۰۳ (src: ۷۲ — ۷ entity، ۷ enum، ۵ سرویس دامنه، ۱۱ رویداد، ۶ پورت مخزن، ۶ آداپتر Persistence، ۸ UseCase، notifier، indexer، PipelineWorkflow، ShopContactSync، ۴ Policy، ۱۰ کنترلر (۱ پرتال + ۷ ادمین + ۱ API + dashboard)، Provider، ۳ command • views: ۲۱ • config: ۲ • migration: ۱ • seed: ۱ • lang: ۴) |
| کیت `kits/crm` | ۱۳ فایل (kit.json، ۳ preset، theme.json + css + layout، roles.json، demo، steps.json، ۲ راهنما، README) |
| تست | ۲۶ (۱۵ Unit + ۱۱ Feature) |
| مسیر | ۵ وب (پرتال) + ۴۵ ادمین + ۳ API |
| مجوز RBAC | ۲۵ کلید `crm.*` + ۷ نقش |

## ۲. انطباق با سناریوهای E2E (DOC-096 §8) و AC (§9)

| سناریو | تست | نتیجه |
|---|---|---|
| E2E-01 lead → qualify → opportunity → won | `testE2E01` — HTTP کامل پنل: مخاطب→سرنخ (score=۴۵ خودکار)→qualify→convert (redirect به فرصت)→نمونهٔ **Workflow در `tvn_process_instances`** + probability مرحله‌ها (۱۰→۲۵→۵۰→۷۵)→won→closed_at+اعلان database+win rate=۱۰۰٪ | ✅ |
| E2E-02 lead scoring | `testE2E02` — referral+شرکت+۱۵۰M → **۶۰** دقیق • فعالیت → **۶۵** • کاهش ارزش → **۵۰** • نمایش امتیاز در صفحه | ✅ |
| E2E-03 ticket با SLA + escalation | `testE2E03` — پرتال مهمان (contact خودکار source=support)→assign+اعلان→پاسخ agent=**pending**→پاسخ مشتری=open→breach→**escalation خودکار** (escalated_at + رویداد + اعلان به support-manager)→**idempotency monitor**→resolve→close • `testSlaPausedWhilePending` — pending با SLA گذشته escalate نمی‌شود | ✅ |
| E2E-04 campaign email | `testE2E04` — audience `{"tags":["vip"]}` → ۲ ارسال/۱ skip (بدون برچسب) + رکورد recipientها + status=sent + **ارسال مجدد بدون تکرار** (uk) | ✅ |
| E2E-05 گزارش pipeline | `testE2E05` — weighted **دقیقاً 200M** (100M×50٪ + 200M×75٪) + total/win-rate + صفحهٔ گزارش + CSV (BOM + سطر stage) + ۴۰۳ نقش بدون مجوز | ✅ |
| E2E-06 sync مشتری از shop | `testE2E06` — `Event::dispatch('shop.order.paid')` → مخاطب source=shop + userId + custom_fields (سفارش/مبلغ) • سفارش دوم → **همان مخاطب** به‌روزرسانی (بدون ارجاع کلاسی به shop) | ✅ |
| AC-CRM-07 Workflow pipeline با forecast درست | در E2E-01/05 — نمونهٔ فرایند واقعی موتور + forecast وزنی int | ✅ |
| AC-CRM-08 SLA breach → escalation خودکار | در E2E-03 + **راستی‌آزمایی محیط واقعی**: `tvn crm:check-sla` روی نصب demo → تیکت TKT-DEMO-00001 escalate + ۲ اعلان (support-manager/crm-admin) | ✅ |

تست‌های تکمیلی: API intake (`POST /api/v1/crm/leads` → JSON با score=۱۵ درست + 422 روی validation) • matrix نقش‌ها (sales-agent فقط سرنخ خود را می‌بیند/ویرایش دیگری ۴۰۳، marketing بدون تیکت، support-agent بدون crash، مهمان redirect) • XSS پرتال (sanitize plain) • **internal note در پرتال مشتری دیده نمی‌شود ولی در پنل بله** • national_id: در DB ≠ plaintext + repository رمزگشایی + ویو `******5678`.

## ۳. تصمیم‌های طراحی

1. **یکپارچگی shop فقط رویدادمحور** — listener روی `shop.order.paid` با payload رشته‌ای + snapshot کاربر از `UserReaderInterface`؛ ماژول crm هیچ ارجاع کلاسی به shop ندارد (کیت‌ها مستقل نصب می‌شوند — `tavaan.shop` در kit.json اختیاری است).
2. **پیام‌های PII** — `national_id` با `Crypt::encrypt(..., AAD='crm.contact.national_id')` (AES-GCM؛ AAD = binding به context). رمزگشایی خراب (کلید چرخیده/دادهٔ قدیمی) → null (best-effort) ولی **رمزنگاری ناموفق → مقدار ذخیره نمی‌شود** (fail-safe: plaintext هرگز روی دیسک نمی‌نشیند).
3. **Workflow نمونه-per-opportunity** — همان الگوی گواهی LMS: lazy-define + start/fire از راه Facade (dynamic، بدون type-hint موتور)؛ اگر موتور در دسترس نبود pipeline دامنه تنها کار می‌کند (best-effort + Log). گذارهای بازگشتی (back_q/back_pr/back_n) برای هم‌پوشانی کامل با ماتریس دامنه.
4. **escalation = ستون روی تیکت + رویداد** (نه فرایند workflow جدا) — نقض SLA یک واقعه است نه جریان چندقسمتی؛ `escalated_at` idempotency را تضمین می‌کند و مصرف‌کننده‌ها (اعلان/گزارش) با رویداد کار می‌کنند. مسیر workflow برای خودِ pipeline فروش خرج شد (AC-CRM-07).
5. **SLA با Clock** — dueAt/now همه از `Tavaan\Support\Time\Clock` (تست با Fake قطعی؛ همان درس prune در گام ۵). pending = `slaRunning()=false` (توقف ساعت، رفتار استاندارد پشتیبانی).
6. **کمپین idempotent با uk گیرنده** — cron هر دقیقه امن است: recipientExists قبل از ارسال + `uk_crm_campaign_recipient` در سطح DB. ارسال = `Mail::raw` با closure (to/subject) — بدون Mailable class (مرز ماژول: فقط Facade).
7. **رویداد `crm.ticket.assigned` افزودنی** — §5 فقط created را دارد ولی تخصیص **پس از** ساخت رخ می‌دهد؛ اعلان کارشناس به رویداد خودش نیاز دارد (payload هم‌شکل created؛ notifier مشترک با kind=نام رویداد).
8. **portal = capability URL** — شمارهٔ تیکت (TKT-date-5hex) کلید دسترسی پیگیری است (بدون حساب کاربری)؛ نوشتن‌ها با Throttle (5/60s ثبت، 10/60s پاسخ) و اولویت تیکت پرتالی به normal/low محدود (تصمیم اولویت با agent).
9. **scoring engagement = فعالیت‌ها** — «بازکردن ایمیل/بازدید» (§3.2) وب‌هوک می‌خواهد؛ در این نگارش تعداد فعالیت‌های ثبت‌شده (call/meeting/…) proxy است — سقف ۱۵ همان مستند. ثبت فعالیت → بازشماری خودکار سرنخ‌های باز.
10. **پول int minor-unit** — value سرنخ/فرصت (ADR-0013)؛ forecast با `floor` و مقایسه‌های int (بدون drift اعشار).

## ۴. نصب واقعی و smoke (HTTP واقعی با `php -S`)

```
tvn rbac:sync                                       → ۲۵ مجوز crm.*
tvn kit:install crm --preset=standard --with-demo   → روی DB موجود (هم‌زیستی lms/shop/blog):
  • ۱ migration (۸ جدول crm_*) • theme → resources/views/vendor/crm + assets
  • نقش‌های جدید: sales-agent/sales-manager/support-agent/support-manager/marketing/crm-admin
  • demo: ۵ مخاطب/۳ سرنخ/۴ فرصت (won/lost/negotiation/proposal)/۳ تیکت (یکی SLA-گذشته)/۲ فعالیت/۱ کمپین + انتصاب ۴ نقش
GET /crm/support → 200 با پوستهٔ کیت (sky + /assets/kits/crm/css/app.css)
GET /lms → 200 • /shop → 200 • /admin/crm → 302   (هم‌زیستی کیت‌ها + گارد auth) ✅
POST /crm/support (با CSRF واقعی + cookie) → 302 → TKT-20261025-0EFA2 → صفحهٔ تیکت 200 ✅
POST /api/v1/crm/leads (JSON) → 200 {lead_id, contact_id, status:new, score:15→30 با ارزش بالا} ✅
tvn crm:check-sla → «⚠ 1 تیکت escalate شد: TKT-DEMO-00001» + ۲ اعلان sla_breached ✅ (AC-CRM-08 واقعی)
```

## ۵. دروازه‌های کیفیت (DoD — DOC-113)

| دروازه | نتیجه |
|---|---|
| `tvn test` (کل) | **1534/1534 سبز — ۱۰۱۴۵ assertion** (QaTools این اجرا در suite پاس شد؛ ۵۰٫۹s / ۵۲۲MB) |
| `tvn arch:check` | ۰ نقض (۱۴۲۸ فایل) — ARCH-003: فقط SDK مجاز در src؛ ارجاع `Engine\Auth/Security\Middleware` فقط در routes (الگوی forum/lms) |
| `tvn security:scan` | ۰ high/critical (۸ med = checksum‌های موجود — **بدون یافتهٔ جدید**) |
| `tvn lint:php` | ۱۹۸۱ فایل، ۰ خطا |
| `tvn module:validate` | ۶۲ manifest معتبر |
| `tvn engine:validate` | ۵۲ موتور، ۰ خطا |

## ۶. بدهی/محدودیت‌های ثبت‌شده

- **SMS log-only** — درایور gateway پیامک در بک‌لاگ Release 1.0 (کمپین sms فعلاً در log ثبت می‌شود).
- **mail parser ورودی** (§7: ایمیل → تیکت) پیاده نشد — مسیر API/پرتال پوشش می‌دهد؛ IMAP-poller در بک‌لاگ.
- **VoIP/شبکهٔ اجتماعی webhook** (§7) — فقط ساختار activity آماده است.
- engagement scoring با فعالیت proxy شده (بازکردن ایمیل/بازدید نیاز به tracking pixel/وب‌هوک دارد — بک‌لاگ).
- satisfaction survey پس از resolve فقط به‌صورت اعلان متنی است (فرم نظرسنجی اختصاصی در بهبود بعدی).
- assign خودکار (round-robin) پیاده نشد — تخصیص دستی توسط manager (config برای قواعد آماده است).
- UI ورود/ثبت‌نام همچنان نبود دارد (شکاف مشترک کیت‌ها برای Release 1.0).

## ۷. وضعیت پس از این گام

- فاز M9: **۶ از ۸ کیت** انجام شد (Blog, News, Shop, Forum, LMS, **CRM**).
- تیکت بعدی طبق DOC-121 §7: **TVN-0097 (Kit: ERP — DOC-097)**.
- پرونده‌های کلیدی:

```
modules/crm/                      — ماژول (module.json، src/، views/، migrations/، seeds/، lang/، tests/، config/، README.md)
kits/crm/                         — کیت (kit.json، presets/، themes/default، seed/، install/، docs/، README.md)
docs/05-applications/crm.md       — §10 وضعیت + بلوک ماشین‌خوان + انحراف‌ها
docs/08-roadmap/agent-backlog.md  — TVN-0096 done • M9: ۲ مانده/۶ انجام
CHANGELOG.md • tavaan.json • core/bootstrap.php • README.md — 0.14.0
.gitignore                        — overlayهای config/crm.php + vendor/crm
```
