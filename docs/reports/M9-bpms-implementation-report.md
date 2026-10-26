# گزارش پیاده‌سازی — M9 گام ۸: کیت BPMS 🔄 (پایان M9)

| متادیتا | مقدار |
|---|---|
| تیکت | TVN-0098 (DOC-098) — آخرین تیکت M9 |
| نگارش | 0.15.0 → **0.16.0** |
| کامیت | `M9 Kit BPMS` |
| ماژول | `modules/bpms` (tavaan.bpms) |
| کیت | `kits/bpms` (tavaan.kit.bpms) |
| تست | **۸۷ جدید** (۶۷ Unit + ۲۰ Feature) — سوئیت کامل **۱۶۷۰ تست / ۰ failure** (۱۰٬۹۵۶ assertion)¹ |
| گیت‌ها | arch:check ✓ (۱۵۷۲ فایل، ۰ نقض) • lint:php ✓ (۲۲۰۱ فایل) • security:scan ✓ (۰ high — ۸ med موجود بدنه، ۰ در bpms) • module:validate ✓ (۶۴) • engine:validate ✓ (۵۲) |

> ¹ در sandbox این اجرا (~۸۴۰MB حافظهٔ آزاد) ۱۰ تست `QaToolsTest` هنگام
> `proc_open` با `Fork failed: Cannot allocate memory` خطای **محیطی** می‌دهند —
> همان ۱۰ تست در اجرای جداگانه **۱۰/۱۰ سبز** (۵۸ assertion). بیس‌لاین
> پیش از تغییرات نیز روی همین sandbox در اجرای کامل ۱۵۸۳/۱۵۸۳ سبز شد؛
> در محیط با حافظهٔ کافی سوئیت کامل سبز است.

## ۱. دامنهٔ تحویل

- **ماژول BPMS** (hexagonal): فرایندساز (draft→publish→activate/retire با
  نسخه‌بندی + نمودار SVG/Mermaid)، فرم‌ساز پویا (schema→validation→رندر)،
  `ProcessEngine` عنصر-محور با مدل توکن (userTask/serviceTask/exclusive+
  parallel gateway/endEvent)، **ExpressionEvaluator امن بدون eval**،
  ActionRegistry، تایمر/escalation زمان‌بندی‌شدهٔ idempotent، پایش
  (KPI/bottleneck/SLA/workload + CSV + SSE)، BpmsNotifier، mirror موتور
  workflow، audit مدیریتی با hash chain — ۶۰ کلاس src، ۷ enum، ۶ رویداد،
  ۵ پورت + ۵ آداپتر، ۴ جدول `bpms_*`، ۱۶ مجوز، ۱۹ ویو RTL، ۱ CLI.
- **کیت**: ۳ preset، تم RTL فیروزه‌ای-سرمه‌ای، ۷ نقش (۵ نقش مستند §7 +
  manager/admin)، demo انتصاب تیم، راهنمای ادمین/کاربر.
- **E2E-01..10** مستند §11 کامل + **AC-BPMS-11/12/13**.
- **`tools/scripts/smoke-bpms.php`**: smoke واقعی HTTP با نشست کوکی‌دار
  (۲۸/۲۸ سبز — درس §۴٫۱-۷ گزارش گام ۷ نهادینه شد).

## ۲. تصمیم‌های معماری

1. **موتور اجرا درون ماژول؛ workflow = لایهٔ mirror** (الگوی LMS/CRM/ERP):
   DOC-098 §2 جداول `bpms_*` و §5 موتور عنصر-محور (serviceTask با اکشن
   ثبت‌شده، gateway با expr، فرم per task، تایمر اکشن-محور) را تعریف کرده —
   این معنا با مدل state/transition و guardهای `can:`-محور موتور workflow
   قابل بیان کامل نیست. مرجع صحت `bpms_*` است؛ `WorkflowBridge` تعریف
   منتشرشده را کامپایل (gatewayها `normal` — مسیریابی خودکار موتور
   غیرفعال) و هر حرکت نمونه را با fire صریح در `tvn_process_instances`
   منعکس می‌کند (best-effort + log-warning). «روی شانهٔ workflow» =
   تاریخچه/visualization یکپارچه با سایر کیت‌ها.
2. **Expression Evaluator دو لایه** (AC-BPMS-12): parse-check در
   `ModelValidator` هنگام publish (طراح خطا را همان‌جا می‌بیند) + eval در
   runtime — lexer/parser/AST بدون eval/تابع/property؛ کاراکترهای تزریق
   (`$`/backtick/`;`/`@`/`#`/`\`/`,`) و فراخوانی تابع و سقف طول/عمق → خطای
   قطعی. اشیاء/منابع از context نشت نمی‌کنند؛ مقایسهٔ ترتیبی فقط هم‌نوع.
3. **توکن‌های موازی در variables** (`_tokens/_joins/_ended`): همان قرارداد
   کلید رزرو موتور workflow — بدون ستون اضافی؛ join با شمارش ورودی و
   **work-queue** (هر توکن دقیقاً یک‌بار پردازش — توکن پارک‌شده هرگز
   بازپردازش نمی‌شود؛ جلوگیری از join زودهنگام).
4. **advance با صف کار به‌جای پیمایش مکرر توکن‌ها**: پیش‌برد قطعی و
   پایان‌پذیر با `advance_limit` (حلقهٔ service task → خطا نه hang).
5. **فرم: normalize پیش از validate** — کلیدهای خارج از schema هرگز در
   متغیرها ادغام نمی‌شوند (🔒 mass-assignment)؛ checkbox ارسال‌نشده =
   false (قرارداد HTML)؛ قواعد از خود schema تولید می‌شوند (§4).
6. **اکشن‌ها فقط از ActionRegistry** (§6) — اکشن ناشناخته →
   `unknown_action` و کل عملیات در `DB::transaction` rollback (بدون اثر
   جانبی)؛ تایمر اکشن سفارشی پس از اجرا مُهر idempotency می‌خورد.
7. **Audit دو سطحی**: `bpms_history` (append-only، گردش فرایند با
   actor/trigger/duration_ms) برای timeline و پایش؛ hash chain موتور audit
   برای **عملیات مدیریتی** (publish/activate/retire/terminate/suspend/
   resume/assign/force-complete) — fail-open + allowlist ادغامی در provider.
8. **claim اتمیک** — `UPDATE … WHERE status='pending'` (رقابت: یک برنده؛
   بازنده `already_claimed`).

## ۳. انحراف‌های مستند (همه در DOC-098 §14 ثبت شد)

| مستند | پیاده‌سازی | دلیل |
|---|---|---|
| «BPMS روی موتور workflow» | موتور ماژول مرجع صحت + mirror روی موتور | §۲ تصمیم ۱ — الگوی همسان ۷ کیت پیشین |
| BIGINT PK (bpms_history) | همان BIGINT؛ بقیه ULID char(26) | استاندارد فریمورک |
| §2 چهار ستون کمتر از نیاز اجرا | افزودنی‌ها (status/created_by/workflow_instance_id/form_id/claimed_by/completed_by/escalated_at/escalated_to/comment/trigger) | چرخهٔ انتشار/audit/escalation — مستند در header migration |
| اکشن‌های §6 (approveLeave…) | داخلی‌های عمومی (mark_done/notify_initiator/…) + ثبت اکشن دامنه‌ای توسط اپ/تست | demo/seed نباید به کد دامنه‌ای خاص وابسته باشد؛ E2E-08 همان الگوی §6 را با اکشن ثبت‌شده آزمایش می‌کند |
| رویداد §10 (۵ مورد) | ۵ مورد + `bpms.process.terminated` | تفکیک پایان غیرطبیعی از completed (status=terminated §2) |
| Sub-process / designer drag-drop | بک‌لاگ 1.0 | خارج از E2E-01..10؛ JSON + پیش‌نمایش SVG/Mermaid کفایت می‌کند |

## ۴. باگ‌های یافت‌شده حین پیاده‌سازی

1. **join زودهنگام در advance حلقه‌ای** (طرح نخست): پیمایش مکرر توکن‌ها
   توکن پارک‌شده در join را بازپردازش و `arriveAtJoin` را دوباره increment
   می‌کرد → رفع با **work-queue** (هر توکن یک‌بار صف می‌شود).
2. **task با assignee=initiator و assignee_id=NULL** در inbox دیده نمی‌شد
   (کوئری `assignee_id = user`) → resolve شناسهٔ آغازگر هنگام ساخت task
   (نوع `initiator` برای canWork/audit حفظ می‌شود).
3. **تغییرات متغیرها از اکشن تایمر persist نمی‌شد** (`ctx->set` روی
   instance حافظه‌ای بدون update) → `instances->update` پس از اکشن سفارشی.
4. فرم مشترک دو شاخهٔ موازی: normalize چک‌باکس ارسال‌نشده را false در
   متغیرها می‌نوشت و شاخهٔ دوم مقدار شاخهٔ اول را **صفر** می‌کرد → در
   تعریف دمو/تست فرم per-شاخه (finance_form/warehouse_form)؛ رفتار
   normalize مستند شد.

## ۵. راستی‌آزمایی عملیاتی (پس از نصب واقعی)

- `php bin/tvn migrate` (۲۳ migration) + `tvn rbac:sync` (۱۶ مجوز bpms.*)
  + `tvn kit:install bpms --with-demo` روی SQLite مشترک: ۲ تعریف
  published/active، ۳ نمونه (completed/running/parallel)، ۶ کار، ۱۵ ردیف
  history، mirror: ۲ `tvn_workflows` + ۳ `tvn_process_instances` ✓
- **HTTP smoke واقعی: ۲۸/۲۸ check سبز** (`php -S` + نشست کوکی‌دار واقعی —
  `tools/scripts/smoke-bpms.php`): ۱۱ صفحهٔ پنل/وب → ۲۰۰ • diagram.svg →
  `image/svg+xml` • kpis.csv → BOM+text/csv • **جریان زندهٔ E2E**: start
  (POST) → صفحهٔ task با فرم پویا (leave_type/days) → دادهٔ نامعتبر
  (days=99) رد و task باز → دادهٔ معتبر → auto-approve → **completed** +
  **mirror completed** + history ۶ ردیف • ماتریس نقش: reader(auditor/
  monitor) → پایش/audit 200، terminate **403**، definitions/create **403** •
  مهمان → 302 • `tvn bpms:check-timers` بدون خطا • audit chain «سالم» در
  صفحهٔ audit.

## ۶. بدهی فنی (ثبت برای 1.0)

- TECH-BPMS-01: Sub-process (زیرفرایند تو‌در‌تو) — موتور workflow دارد؛
  BPMS element-type و semantics لازم دارد.
- TECH-BPMS-02: Designer گرافیکی drag-drop (canvas) — فعلاً JSON + SVG
  زنده؛ کامپایل schema به فرم بصری.
- TECH-BPMS-03: چند توکن همزمان روی یک userTask (یک کار per state) —
  سناریوهای BPMN پیشرفته (multi-instance task).
- TECH-BPMS-04: گزارش‌های موتور report (xlsx) برای پایش — فعلاً CSV.
- TECH-BPMS-05: صف/worker برای service taskهای سنگین (اجرای async از راه
  queue engine) — فعلاً همگام در تراکنش.

## ۷. وضعیت M9 — پایان فاز ✅

با این گام هر **۸ کیت** M9 (blog/news/shop/forum/lms/crm/erp/bpms) پیاده،
نصب‌شده و smoke-شده‌اند؛ بک‌لاگ ۶۳/۶۳ done. معیارهای «انتشار 1.0»
(DOC-120 §6) از منظر کیت‌ها محقق است: ۵۳ موتور پیاده، همهٔ ۸ کیت
نصب‌شدنی با demo، `tvn` CLI کامل (شامل `kit:install`/`bpms:check-timers`)،
مستندات + راهنمای مهاجرت/Quick Start در docs. بنچمارک رقابتی و
docs.tavaan.dev (میزبانی) در مرحلهٔ انتشار باقی می‌مانند.
