# DOC-098 — کاربرد BPMS (مدیریت فرایندهای کسب‌وکار) 🔄

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-098 |
| سطح | سازمانی |
| معماری | `hexagonal` |
| ماژول‌ها | core-user, bpms, workflow, form-builder, notification, report, audit |
| موتورها | workflow, rbac, queue, realtime, report |

---

## ۱. دامنه
سامانهٔ طراحی، اجرا و پایش فرایندهای کسب‌وکار: فرایندساز (Designer)، فرم‌ساز،
موتور اجرا، کارهای انسانی، قوانین، و داشبورد پایش.

> BPMS روی شانهٔ موتور `workflow` (DOC-071) ساخته می‌شود اما لایهٔ **طراحی بصری**،
> **فرم‌ساز پویا** و **پایش** را اضافه می‌کند.

### ۱٫۱ اجزا
```
Process Designer (بصری) → Process Definition (JSON/BPMN-like)
Form Builder (پویا)     → Form Schema (JSON)
Process Engine          → Instances + Tasks + Timers
Monitoring Dashboard    → KPI, Bottleneck, SLA
```

## ۲. مدل داده
```sql
-- تعریف فرایند (طراحی‌شده)
CREATE TABLE bpms_process_definitions (
  id CHAR(26) PRIMARY KEY, key VARCHAR(100), name VARCHAR(190),
  version INT, definition JSON NOT NULL,   -- گراف states/transitions/gateways
  form_schema JSON,                        -- فرم‌های هر task
  is_active TINYINT(1), created_at DATETIME(3),
  UNIQUE KEY uk_key_version (key, version)
);

-- نمونهٔ فرایند (اجرا)
CREATE TABLE bpms_process_instances (
  id CHAR(26) PRIMARY KEY, definition_id CHAR(26) NOT NULL,
  business_key VARCHAR(100),               -- ارجاع به موجودیت کسب‌وکار
  initiator_id BIGINT UNSIGNED,
  current_state VARCHAR(100), status VARCHAR(20),  -- running|completed|suspended|terminated
  variables JSON, started_at DATETIME(3), completed_at DATETIME(3),
  KEY idx_def_status (definition_id, status)
);

-- کارهای انسانی
CREATE TABLE bpms_tasks (
  id CHAR(26) PRIMARY KEY, instance_id CHAR(26) NOT NULL,
  state_id VARCHAR(100), title VARCHAR(255),
  assignee_type VARCHAR(30),               -- user|role|group|initiator
  assignee_id VARCHAR(64), 
  form_data JSON, status VARCHAR(20) DEFAULT 'pending',
  priority VARCHAR(20) DEFAULT 'normal',
  due_at DATETIME(3), claimed_at DATETIME(3), completed_at DATETIME(3),
  KEY idx_assignee (assignee_id, status), KEY idx_instance (instance_id)
);

-- تاریخچه
CREATE TABLE bpms_history (
  id BIGINT PK, instance_id CHAR(26), from_state VARCHAR(100),
  to_state VARCHAR(100), transition VARCHAR(100),
  actor_id BIGINT, occurred_at DATETIME(3), duration_ms INT
);
```

## ۳. تعریف فرایند (JSON Schema)
```json
{
  "key": "leave-request",
  "name": "درخواست مرخصی",
  "version": 1,
  "startEvent": "start",
  "elements": [
    {"id":"start","type":"startEvent","next":"submit"},
    {"id":"submit","type":"userTask","name":"ثبت درخواست",
     "assignee":"initiator","form":"leave_form","next":"check_days"},
    {"id":"check_days","type":"exclusiveGateway","name":"بررسی تعداد روز",
     "conditions":[
        {"expr":"variables.days <= 2","next":"auto_approve"},
        {"default":true,"next":"manager_approval"}
     ]},
    {"id":"auto_approve","type":"serviceTask","name":"تأیید خودکار",
     "action":"approveLeave","next":"notify"},
    {"id":"manager_approval","type":"userTask","name":"تأیید مدیر",
     "assignee":"role:manager","form":"approval_form","due":"P2D","next":"decision"},
    {"id":"decision","type":"exclusiveGateway",
     "conditions":[
        {"expr":"variables.approved","next":"notify"},
        {"default":true,"next":"reject"}
     ]},
    {"id":"notify","type":"serviceTask","action":"sendNotification","next":"end"},
    {"id":"reject","type":"serviceTask","action":"sendRejection","next":"end"},
    {"id":"end","type":"endEvent"}
  ],
  "timers": [
    {"on":"manager_approval","after":"P2D","action":"escalate"}
  ]
}
```

## ۴. فرم‌ساز پویا (Form Builder)
```json
// form_schema برای task 'submit'
{
  "id": "leave_form",
  "fields": [
    {"name":"leave_type","type":"select","label":"نوع مرخصی",
     "options":["استحقاقی","استعلاجی","بدون حقوق"],"required":true},
    {"name":"days","type":"number","label":"تعداد روز","min":1,"max":30,"required":true},
    {"name":"from_date","type":"jalali_date","label":"از تاریخ","required":true},
    {"name":"to_date","type":"jalali_date","label":"تا تاریخ","required":true},
    {"name":"reason","type":"textarea","label":"دلیل"}
  ]
}
```
→ فرم در زمان اجرا رندر می‌شود (با `view` engine) و داده در `variables`/`form_data` ذخیره.
→ validation از `form_schema` (موتور validation) تولید می‌شود.

## ۵. موتور اجرا (بر پایهٔ workflow)
```php
final class ProcessEngine
{
    public function start(string $definitionKey, array $variables, ?User $initiator = null): ProcessInstance
    {
        $def = $this->definitions->findActive($definitionKey);
        $instance = ProcessInstance::create($def, $variables, $initiator);
        $this->advance($instance);          // حرکت به اولین task
        return $instance;
    }

    public function completeTask(string $taskId, array $formData, User $actor): void
    {
        $task = $this->tasks->findOrFail($taskId);
        $this->authorize($task, $actor);
        $this->validateForm($task, $formData);

        $task->complete($formData, $actor);
        $instance = $task->instance();
        $instance->mergeVariables($formData);

        $this->advance($instance);          // حرکت بعدی بر اساس transition/gateway
    }

    private function advance(ProcessInstance $instance): void
    {
        $current = $instance->currentState();
        $element = $instance->definition()->element($current);

        match ($element->type) {
            'userTask'      => $this->createTask($instance, $element),
            'serviceTask'   => $this->runService($instance, $element),
            'exclusiveGateway' => $this->evaluateGateway($instance, $element),
            'parallelGateway'  => $this->fork($instance, $element),
            'endEvent'      => $instance->complete(),
            default         => throw new ProcessException(),
        };
    }

    private function evaluateGateway(ProcessInstance $instance, Element $gw): void
    {
        foreach ($gw->conditions as $cond) {
            if (isset($cond['expr']) && $this->evaluateExpression($cond['expr'], $instance->variables())) {
                $instance->moveTo($cond['next']);
                $this->advance($instance);
                return;
            }
            if (isset($cond['default']) && $cond['default']) {
                $instance->moveTo($cond['next']);
                $this->advance($instance);
                return;
            }
        }
    }
}
```

### ۵٫۱ Expression Evaluator (امن) 🔒
```php
// 🔒 هرگز eval() — یک evaluator ساده و امن
final class ExpressionEvaluator
{
    public function evaluate(string $expr, array $context): bool
    {
        // پشتیبانی: variables.x > 5, variables.approved, x == 'a' && y
        $tokens = $this->tokenize($expr);      // lexer
        $ast = $this->parse($tokens);          // parser (بدون eval)
        return $this->evalAst($ast, $context);
    }
}
```
🔒 **ممنوعیت `eval()`** — یک mini-expression-language با lexer/parser امن.

## ۶. Service Tasks (اتصال به ماژول‌ها)
```php
// action: 'approveLeave' → یک handler ثبت‌شده
ProcessEngine::registerAction('approveLeave', function (ProcessContext $ctx): void {
    $leave = Leave::find($ctx->get('leave_id'));
    $leave->approve();
    $leave->save();
});
```
🔒 Actions از قبل ثبت می‌شوند (نه از ورودی کاربر).

## ۷. نقش‌ها
| نقش | مجوز |
|---|---|
| `process-designer` | طراحی فرایند/فرم |
| `process-admin` | مدیریت نمونه‌ها، terminate |
| `task-assignee` | انجام taskهای assigned |
| `monitor` | داشبورد پایش (خواندن) |
| `auditor` | تاریخچه + audit |

## ۸. داشبورد پایش (Report + Realtime)
- نمونه‌های فعال/تکمیل‌شده
- زمان متوسط هر task (bottleneck)
- SLA compliance
- بار کاری هر assignee
- نمودار جریان (visualize definition)
- Realtime: task جدید با SSE

## ۹. Timer و Escalation
```
task با due=P2D → اگر در ۲ روز انجام نشد:
  → escalate (به مدیر)
  → یا auto-action (بر اساس تعریف)
اجرا با موتور schedule (هر دقیقه timerها بررسی می‌شوند)
```

## ۱۰. رویدادها
| رویداد | مصرف‌کننده |
|---|---|
| `bpms.process.started` | notification (initiator) |
| `bpms.task.created` | notification (assignee), realtime |
| `bpms.task.completed` | advance, history |
| `bpms.process.completed` | notification, report |
| `bpms.task.escalated` | notification (مدیر) |

## ۱۱. تست E2E
| ID | سناریو |
|---|---|
| E2E-01 | فرایند مرخصی: submit → auto-approve (≤۲ روز) |
| E2E-02 | فرایند مرخصی: submit → manager → approve |
| E2E-03 | manager reject → notify |
| E2E-04 | exclusive gateway با expression |
| E2E-05 | parallel gateway (fork/join) |
| E2E-06 | timer escalation پس از due |
| E2E-07 | فرم پویا + validation |
| E2E-08 | service task → اتصال به ماژول |
| E2E-09 | history + audit کامل |
| E2E-10 | expression evaluator امن (بدون eval) |

## ۱۲. معیار پذیرش 🧪
- AC-BPMS-01..10.
- AC-BPMS-11: Designer خروجی JSON معتبر تولید می‌کند که Engine اجرا کند.
- AC-BPMS-12: Expression evaluator بدون `eval` و مقاوم در برابر injection.
- AC-BPMS-13: Timer/escalation با schedule کار می‌کند.

## ۱۳. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| BPMN 2.0 | استاندارد فرایند | زیرمجموعهٔ کاربردی، JSON ساده |
| Camunda/Flowable | موتور اجرا | خودنوشته، سبک، بدون Java |
| Activiti | user task/gateway | — |
| ProcessMaker | form builder + designer | فرم‌ساز JSON بومی |
