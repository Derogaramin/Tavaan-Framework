# ماژول BPMS — `tavaan.bpms` 🔄

> طراحی، اجرا و پایش فرایندهای کسب‌وکار — DOC-098 (TVN-0098، آخرین گام M9).
> نصب از راه کیت: `php bin/tvn kit:install bpms --with-demo`

## دامنه

```
Process Designer (JSON + SVG/Mermaid) → bpms_process_definitions (نسخه‌دار)
Form Builder (schema پویا)            → form_schema → validation + رندر
Process Engine (عنصر-محور + توکن)     → bpms_process_instances / bpms_tasks
Monitoring (KPI/SLA/bottleneck)       → داشبورد + CSV + SSE
```

## معماری

Hexagonal (Domain → Application → Infrastructure → Http). مرجع صحت اجرا
جداول `bpms_*` است؛ **موتور workflow لایهٔ تاریخچه/visualization** است
(`WorkflowBridge` — mirror best-effort با log-warning، الگوی LMS/CRM/ERP؛
gatewayها در mirror `normal` کامپایل می‌شوند و مسیر واقعی با fire صریح
حرکت می‌کند).

🔒 ARCH-003: فقط Core/Facadeها + `Audit\AuditEvent` و `Console\Command`
(allow صریح) — هیچ import دیگری از `Tavaan\Engine\*`.

## موتور اجرا (DOC-098 §5)

- **عنصرها**: `startEvent` / `userTask` (assignee: `initiator`|`user:ID`|
  `role:KEY`|`group:KEY` + form + due + priority) / `serviceTask` (action
  ثبت‌شده) / `exclusiveGateway` (conditions با expr + default) /
  `parallelGateway` (fork: [...] یا join با چند ورودی) / `endEvent`
- **مدل توکن**: `_tokens/_joins/_ended` در variables (کلیدهای رزرو —
  mergeVariables هرگز آن‌ها را از ورودی نمی‌نویسد)
- **advance با work-queue**: هر توکن یک‌بار پردازش؛ توکن پارک‌شده
  (userTask/join ناقص) بازپردازش نمی‌شود؛ `advance_limit` محافظ حلقه
- **completeTask**: authorize (user/role/group/initiator/anyone + override
  مدیر) → normalize+validate فرم → merge متغیرها → history (actor/
  duration_ms) → advance
- **claim اتمیک** (UPDATE شرطی) • **delegate** • **suspend/resume/
  terminate** (لغو کارهای باز + mirror cancel)
- تراکنش: همه‌چیز در `DB::transaction`؛ رویدادها **پس از commit**

## Expression Evaluator امن (AC-BPMS-12) 🔒

lexer + recursive-descent parser + eval روی AST — **بدون `eval()`**:

- مجاز: `variables.days <= 2` • `==`/`!=`/`===`/`!==` • `<`/`<=`/`>`/`>=` •
  `&&`/`||`/`!` (و `and`/`or`/`not`) • `+ - * / %` • پرانتز (عمق ≤ ۳۲) •
  عدد/رشته/true/false/null • مسیر dotted روی آرایه‌ها
- ممنوع (خطای قطعی `invalid_expression`): فراخوانی تابع • `$` • backtick •
  `;` • `@` • `#` • `\` • `,` • طول > ۲۰۰۰ • division-by-zero • مقایسهٔ
  ترتیبی نوع‌ناهمگن • اشیاء/منابع در context (→ null/false، بدون نشت)
- **دو لایه**: parse-check هنگام publish (ModelValidator) + eval در runtime

## فرم‌ساز پویا (§4)

انواع: text/textarea/number/select/checkbox/jalali_date/date/email/mobile/
hidden → قواعد validation متناظر (required/nullable/string/numeric/min/max/
in/boolean/jalali_date/date/email/iran_mobile) + `normalize()` (رشته→عدد،
checkbox→bool، **حذف کلیدهای خارج از schema**) + رندر با
`bpms::partials.dynamic-form`.

## اکشن‌ها (§6)

`ActionRegistry` — **از پیش ثبت‌شده، نه از ورودی کاربر**:

```php
$app->make(\Modules\Bpms\Application\UseCases\ActionRegistry::class)
    ->register('approveLeave', function (\Modules\Bpms\Application\UseCases\ProcessContext $ctx): void {
        // اتصال به ماژول دامنهٔ اپلیکیشن
        $ctx->set('leave_approved', true);
    });
```

داخلی‌ها: `noop` / `log` / `mark_done` / `notify_initiator` / `escalate`
(تایمر). اکشن ناشناخته → `unknown_action` + rollback.

## تایمر/escalation (§9 — AC-BPMS-13)

`tvn bpms:check-timers` (schedule هر دقیقه): کارهای بازِ سررسیدگذشته و
(بدون مهر `escalated_at`) → تایمر تعریف → `escalate` (reassign به نقش
`bpms.timers.escalate_role` + رویداد + اعلان) یا اکشن ثبت‌شده + مُهر
`escalated_at` (idempotent). `DurationParser` خالص: `P2D`/`PT30M`/
`P1DT2H30M`/`2 days`.

## رویدادها (§10) و اعلان/realtime

`bpms.process.started|completed|terminated` • `bpms.task.created|completed|
escalated` → `BpmsNotifier` (mail+database — initiator/assignee/نقش‌ها) +
`TaskBroadcaster` (SSE کانال `bpms` و `bpms.instance.{ULID}` — فقط فیلدهای
سبک، بدون متغیرها).

## پایش (§8)

`MonitoringCalculator` (خالص) + `MonitoringService`: شمارش وضعیت‌ها •
میانگین چرخه • bottleneck (میانگین/بیشینه مدت per state) • SLA compliance
• بار کاری assignee • فعالیت اخیر • CSV (BOM+ضد injection). متغیرهای حساس
در ویوها mask (`bpms.masked_variables`).

## مسیرها

| مسیر | دسترسی |
|---|---|
| `/admin/bpms` | داشبورد پایش (+`/reports/kpis.csv`) — `bpms.monitor.view` |
| `/admin/bpms/definitions*` | فرایندساز (create/edit/publish/activate/retire + `diagram.svg`) — `bpms.definition.*` |
| `/admin/bpms/instances*` | نمونه‌ها (start/suspend/resume/terminate) — `bpms.instance.*` |
| `/admin/bpms/tasks*` | نمای مدیر کارها (assign/force-complete) — `bpms.task.view-all`/`assign` |
| `/admin/bpms/audit` | ردپای audit — `bpms.audit.view` |
| `/bpms/tasks*` | inbox + claim/complete/delegate — `bpms.task.*` |
| `/bpms/processes*` ، `/bpms/instances*` | کاتالوگ/آغاز + نمونه‌های من — `bpms.instance.start`/`view` |

## جداول (۴ — DOC-098 §2)

`bpms_process_definitions` (definition/form_schema JSON + status/is_active +
uk key/version) • `bpms_process_instances` (variables + توکن‌های رزرو +
workflow_instance_id) • `bpms_tasks` (assignee/form/due/claim/escalation) •
`bpms_history` (append-only: from/to/transition/actor/trigger/duration_ms)

## تست

`modules/bpms/tests/` — ۶۷ Unit (ExpressionEvaluator/DurationParser/
ProcessModel+Validator/FormSchema/ماتریس موجودیت‌ها/MonitoringCalculator/
Compiler/Diagram) + ۲۰ Feature (**E2E-01..10 §11** + AC-BPMS-11 چرخهٔ
HTTP + نقش‌ها + mirror + DoS + مدیریت نمونه). smoke واقعی HTTP:
`tools/scripts/smoke-bpms.php`.

## محدودیت‌های v1 (بک‌لاگ 1.0)

Sub-process • designer drag-drop • multi-instance task (چند توکن روی یک
userTask) • گزارش xlsx • service task async روی queue.
