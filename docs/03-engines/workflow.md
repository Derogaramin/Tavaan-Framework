# DOC-071 — موتور Workflow | گردش‌کار و BPM

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-071 |
| Engine ID | `tavaan.engine.workflow` |
| Namespace | `Tavaan\Engine\Workflow` |
| اولویت | **P2** — فاز M7 |
| وابستگی | `event`, `database`, `rbac`, `queue` |
| Facade | `Workflow` |
| LOC | ۲۰۰۰ |

## ۱. هدف
موتور گردش‌کار/BPM برای فرایندهای سازمانی (BPMS/ERP): State Machine، Transition با
Guard/Action، Timer، Human Task، Fork/Join، Sub-Process، History و Visualization.

## ۲. مفاهیم
| واژه | تعریف |
|---|---|
| **Process Definition** | قالب فرایند (states + transitions) |
| **Process Instance** | یک اجرای فرایند |
| **State/Node** | وضعیت (Start/Task/Gateway/Event/End) |
| **Transition** | گذار بین stateها |
| **Guard** | شرط گذار |
| **Action** | عمل هنگام گذار |
| **Task** | کار انسانی (نیازمند assignee) |
| **Timer** | رویداد زمان‌دار |
| **Token** | نشانگر جریان (برای Fork/Join) |

## ۳. تعریف فرایند (DSL)
```php
Workflow::define('order-approval', function (ProcessBuilder $p): void {
    $p->start('draft');

    $p->state('pending_review', type: StateType::UserTask)
        ->assignee('role:shop-manager')
        ->form('admin/orders/review');

    $p->state('approved');
    $p->state('rejected', type: StateType::End);
    $p->state('shipped');
    $p->state('completed', type: StateType::End);

    $p->exclusiveGateway('check_amount');

    $p->transition('submit', from: 'draft', to: 'pending_review')
        ->action(fn (ProcessContext $c) => $c->notifyAssignee());

    $p->transition('review', from: 'pending_review', to: 'check_amount')
        ->guard(fn (ProcessContext $c) => $c->user()->can('shop.order.review'));

    $p->transition('approve', from: 'check_amount', to: 'approved')
        ->guard(fn (ProcessContext $c) => $c->get('amount') < 10_000_000);
    $p->transition('escalate', from: 'check_amount', to: 'manager_review')
        ->guard(fn (ProcessContext $c) => $c->get('amount') >= 10_000_000);

    $p->transition('reject', from: 'pending_review', to: 'rejected');
    $p->transition('ship', from: 'approved', to: 'shipped');
    $p->transition('complete', from: 'shipped', to: 'completed');

    // Timer
    $p->timerEvent('auto_reject', after: '3 days', on: 'pending_review', to: 'rejected');

    // Fork/Join (موازی)
    $p->parallelGateway('split');
    $p->transition('start_parallel', from: 'approved', to: 'split');
    $p->transition('to_packing', from: 'split', to: 'packing');
    $p->transition('to_invoice', from: 'split', to: 'invoicing');
    $p->parallelGateway('join');
    $p->transition('join1', from: 'packing', to: 'join');
    $p->transition('join2', from: 'invoicing', to: 'join');
    $p->transition('after_join', from: 'join', to: 'shipped');
});
```

### ۳٫۱ JSON/Schema تعریف (برای Designer)
```json
{
  "id": "order-approval",
  "version": 1,
  "states": [
    {"id":"draft","type":"start"},
    {"id":"pending_review","type":"user_task","assignee":"role:shop-manager"},
    {"id":"approved","type":"normal"},
    {"id":"rejected","type":"end"}
  ],
  "transitions": [
    {"id":"submit","from":"draft","to":"pending_review"},
    {"id":"review","from":"pending_review","to":"approved","guard":"can:shop.order.review"}
  ],
  "timers": [
    {"id":"auto_reject","after":"P3D","on":"pending_review","to":"rejected"}
  ]
}
```

## ۴. Execution Engine
```php
final class ProcessInstance
{
    public function fire(string $transitionId, array $input = []): void
    {
        $transition = $this->definition->transition($transitionId);
        $currentState = $this->currentState();

        // 1. بررسی Guard
        if (!$this->checkGuards($transition, $input)) {
            throw new IllegalTransitionException(
                messageId: 'workflow.illegal_transition',
                context: ['from'=>$currentState, 'transition'=>$transitionId],
            );
        }
        // 2. بررسی مجوز کاربر
        $this->authorize($transition);

        // 3. اجرای Actionها
        $this->runActions($transition, $input);

        // 4. حرکت Token
        $target = $transition->to();
        if ($this->definition->isGateway($target)) {
            $this->handleGateway($target);     // fork/join/exclusive
        } else {
            $this->moveTo($target);
        }

        // 5. ثبت History + Event
        $this->history->record($transitionId, $currentState, $target);
        $this->events->dispatch(new ProcessTransitioned($this->id, $transitionId, $target));

        // 6. اگر state پایانی → complete
        if ($this->definition->isEnd($target)) {
            $this->complete();
        }
    }
}
```

## ۵. جداول
```sql
CREATE TABLE tvn_workflows (
  id CHAR(26) PRIMARY KEY, name VARCHAR(100) NOT NULL,
  version INT NOT NULL, definition JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME(3) NOT NULL
);
CREATE TABLE tvn_process_instances (
  id CHAR(26) PRIMARY KEY, workflow_id CHAR(26) NOT NULL,
  subject_type VARCHAR(190), subject_id VARCHAR(64),   -- موجود مرتبط (Order)
  current_state VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL,
  variables JSON, started_at DATETIME(3), completed_at DATETIME(3),
  KEY idx_subject (subject_type, subject_id), KEY idx_status (status)
);
CREATE TABLE tvn_process_tasks (
  id CHAR(26) PRIMARY KEY, instance_id CHAR(26) NOT NULL,
  state_id VARCHAR(100) NOT NULL, assignee_type VARCHAR(50), assignee_id VARCHAR(64),
  status VARCHAR(20) NOT NULL, due_at DATETIME(3), completed_at DATETIME(3),
  KEY idx_instance (instance_id), KEY idx_assignee (assignee_id, status)
);
CREATE TABLE tvn_process_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, instance_id CHAR(26) NOT NULL,
  from_state VARCHAR(100), to_state VARCHAR(100), transition VARCHAR(100),
  user_id BIGINT NULL, occurred_at DATETIME(3) NOT NULL,
  KEY idx_instance (instance_id)
);
```

## ۶. Timer (با Schedule)
```php
// tvn workflow:timers (هر دقیقه)
final class TimerProcessor
{
    public function process(): void
    {
        $dueTimers = $this->findDueTimers();
        foreach ($dueTimers as $timer) {
            $instance = $this->instances->find($timer->instance_id);
            $instance->fire($timer->transition, ['trigger'=>'timer']);
        }
    }
}
```

## ۷. Human Task و Assignee
```php
$task = Task::where('assignee_id', $userId)->where('status', 'pending')->get();
$task->claim($userId);
$task->complete(['decision'=>'approved', 'comment'=>'...']);
$task->delegate($otherUser);
```

## ۸. امنیت
| تهدید | کنترل |
|---|---|
| گذار غیرمجاز | Guard + `authorize()` (rbac) |
| تغییر state مستقیم | فقط از طریق `fire()` |
| نشت متغیرها | `variables` با mask برای حساس‌ها |
| DoS با instance زیاد | limit per workflow |

## ۹. تست
| T-01 | تعریف فرایند + validation |
| T-02 | fire با guard رد → IllegalTransition |
| T-03 | Exclusive gateway (branch درست) |
| T-04 | Parallel fork/join (هر دو token) |
| T-05 | User task + assignee + claim |
| T-06 | Timer |
| T-07 | History ثبت |
| T-08 | مجوز (rbac) روی transition |
| T-09 | Sub-process |
| T-10 | JSON definition load |

## ۱۰. معیار پذیرش 🧪
- AC-WF-01..10. AC-WF-11: Visualizer از definition نمودار تولید می‌کند.

## ۱۱. مراجع
| منبع | تفاوت |
|---|---|
| BPMN 2.0 | مفاهیم (Gateway/Event/Task) | زیرمجموعهٔ کاربردی، DSL ساده |
| Camunda/Flowable | Execution engine | خودنوشته، سبک |
| winzou/state-machine | State machine | افزودن Timer/Human Task/History |
| Laravel Workflow | — | — |

## ۱۲. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.workflow.v1
engine:
  id: tavaan.engine.workflow
  namespace: Tavaan\Engine\Workflow
  path: engines/workflow
  priority: P2
  phase: M7
  depends_on: [event, database, rbac, queue]
  facade: {name: Workflow, accessor: workflow}
  tables: [tvn_workflows, tvn_process_instances, tvn_process_tasks, tvn_process_history]
  events_emitted: [workflow.started, workflow.transitioned, workflow.completed, workflow.task.created]
  config_file: config/workflow.php
  public_api:
    - class: WorkflowManager
      methods: [define, start, instance, definitions]
    - class: ProcessInstance
      methods: [fire, currentState, can, variables, complete, cancel]
  loc_estimate: 2000
```
