<?php

declare(strict_types=1);

namespace Modules\Bpms\Tests\Feature;

use Modules\Bpms\Application\UseCases\ActionRegistry;
use Modules\Bpms\Application\UseCases\DesignerService;
use Modules\Bpms\Application\UseCases\ProcessContext;
use Modules\Bpms\Application\UseCases\ProcessEngine;
use Modules\Bpms\Application\UseCases\TimerService;
use Modules\Bpms\Domain\Entities\ProcessDefinition;
use Modules\Bpms\Domain\Enums\InstanceStatus;
use Modules\Bpms\Domain\Enums\TaskStatus;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Modules\Bpms\Domain\Repositories\HistoryRepositoryInterface;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E ماژول BPMS — سناریوهای DOC-098 §11 (E2E-01..10) + AC-BPMS-12/13.
 *
 * مسیر واقعی سرویس‌ها + SQLite in-memory + RBAC واقعی + موتور workflow
 * واقعی (mirror) — بدون I/O خارجی.
 */
final class BpmsE2ETest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private DatabaseUserRepository $users;

    private RoleManager $roles;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $app = $this->app();
        $config = $app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('session.csrf.except', ['admin/*', 'bpms/*']);
        $config->set('app.url', 'http://localhost');
        $config->set('mail.default', 'array');
        $config->set('bpms.notifications', [
            'process_started' => true, 'task_created' => true, 'task_completed' => false,
            'process_completed' => true, 'task_escalated' => true,
            'escalate_notify_roles' => ['process-admin', 'admin'],
        ]);

        $this->users = $app->make(DatabaseUserRepository::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    // ─── E2E-01: submit → auto-approve (days ≤ 2) ─────────────────────────

    public function testE2E01_LeaveAutoApprovePath(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $calls = [];
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();

        $engine = $this->engine();
        $instance = $engine->start('leave-request', [], $employee->id, 'LEAVE-1');

        // task ثبت درخواست برای initiator ساخته شد
        $tasks = $engine->openTasksOf($instance->id());
        self::assertCount(1, $tasks);
        self::assertSame('submit', $tasks[0]->stateId());

        // تکمیل فرم مرخصی با ۱ روز → مسیر auto-approve
        $engine->completeTask($tasks[0]->id(), [
            'leave_type' => 'استحقاقی',
            'days' => 1,
            'from_date' => '1405/08/10',
            'to_date' => '1405/08/10',
            'reason' => 'امور شخصی',
        ], $employee->id, $engine->rolesOf($employee->id));

        $fresh = $this->app()->make(InstanceRepositoryInterface::class)->findById($instance->id());
        self::assertNotNull($fresh);
        self::assertSame(InstanceStatus::Completed, $fresh->status());
        self::assertSame(true, $fresh->getVariable('leave_approved'));   // اثر اکشن approveLeave
        self::assertSame(1, $fresh->getVariable('days'));                // دادهٔ فرم در متغیرها
        self::assertSame(['approveLeave', 'sendNotification'], $calls);  // auto_approve → notify

        // رویداد process.completed منتشر شد
        self::assertTrue(DB::table('bpms_history')->where('instance_id', $instance->id())->exists());
    }

    // ─── E2E-02: submit → manager → approve ───────────────────────────────

    public function testE2E02_ManagerApprovesLeave(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $manager = $this->user('manager@bpms.test', 'مدیر', 'manager');
        $calls = [];
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();

        $completed = null;
        Event::listen('bpms.process.completed', static function (string $event, mixed $payload) use (&$completed): void {
            $completed = (array) $payload;
        });

        $engine = $this->engine();
        $instance = $engine->start('leave-request', [], $employee->id, 'LEAVE-2');

        // submit با ۵ روز → مسیر مدیر
        $submit = $engine->openTasksOf($instance->id())[0];
        $engine->completeTask($submit->id(), [
            'leave_type' => 'استحقاقی', 'days' => 5,
            'from_date' => '1405/09/01', 'to_date' => '1405/09/05',
        ], $employee->id, $engine->rolesOf($employee->id));

        $managerTasks = $engine->openTasksOf($instance->id());
        self::assertCount(1, $managerTasks);
        $task = $managerTasks[0];
        self::assertSame('manager_approval', $task->stateId());
        self::assertSame('role', $task->assigneeType()->value);
        self::assertSame('manager', $task->assigneeId());
        self::assertNotNull($task->dueAt());          // due=P2D
        self::assertSame('high', $task->priority()->value);

        // کاربر بدون نقش manager → رد
        $other = $this->user('other@bpms.test', 'دیگری', 'task-assignee');
        try {
            $engine->completeTask($task->id(), ['approved' => '1'], $other->id, $engine->rolesOf($other->id));
            self::fail('باید not_assignee می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('not_assignee', $e->reason());
        }

        // claim توسط مدیر + تکمیل با approved
        $engine->claim($task->id(), $manager->id, $engine->rolesOf($manager->id));
        $claimed = $this->app()->make(TaskRepositoryInterface::class)->findById($task->id());
        self::assertNotNull($claimed);
        self::assertSame(TaskStatus::Claimed, $claimed->status());

        $engine->completeTask($task->id(), ['approved' => '1', 'manager_note' => 'بلامانع است'], $manager->id, $engine->rolesOf($manager->id));

        $fresh = $this->app()->make(InstanceRepositoryInterface::class)->findById($instance->id());
        self::assertNotNull($fresh);
        self::assertSame(InstanceStatus::Completed, $fresh->status());
        self::assertSame(true, $fresh->getVariable('approved'));
        self::assertSame(['sendNotification'], $calls);
        self::assertNotNull($completed);
        self::assertSame($instance->id(), $completed['instance_id']);

        // اعلان database برای task.created به کاربران نقش manager
        self::assertTrue(DB::table('tvn_notifications')
            ->where('notifiable_id', (string) $manager->id)
            ->where('data', 'LIKE', '%bpms.task.created%')
            ->exists());

        // تکمیل task در DB ثبت شد
        $done = $this->app()->make(TaskRepositoryInterface::class)->findById($task->id());
        self::assertNotNull($done);
        self::assertSame(TaskStatus::Completed, $done->status());
        self::assertSame($manager->id, $done->completedBy());
        self::assertSame(true, $done->formData()['approved']);
    }

    // ─── E2E-03: manager reject → notify ──────────────────────────────────

    public function testE2E03_ManagerRejectsLeave(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $manager = $this->user('manager@bpms.test', 'مدیر', 'manager');
        $calls = [];
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();

        $engine = $this->engine();
        $instance = $engine->start('leave-request', [], $employee->id);
        $submit = $engine->openTasksOf($instance->id())[0];
        $engine->completeTask($submit->id(), [
            'leave_type' => 'بدون حقوق', 'days' => 10,
            'from_date' => '1405/09/01', 'to_date' => '1405/09/10',
        ], $employee->id, $engine->rolesOf($employee->id));

        $task = $engine->openTasksOf($instance->id())[0];
        // approved خاموش (checkbox ارسال‌نشده = false) → default → reject
        $engine->completeTask($task->id(), ['manager_note' => 'با توجه به اوج کاری'], $manager->id, $engine->rolesOf($manager->id));

        $fresh = $this->app()->make(InstanceRepositoryInterface::class)->findById($instance->id());
        self::assertNotNull($fresh);
        self::assertSame(InstanceStatus::Completed, $fresh->status());
        self::assertSame(false, $fresh->getVariable('approved'));
        self::assertSame(['sendRejection'], $calls);
    }

    // ─── E2E-04: مرز gateway انحصاری (days=2 vs days=3) ──────────────────

    public function testE2E04_ExclusiveGatewayBoundary(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $calls = [];
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();
        $engine = $this->engine();

        // days=2 → auto-approve (شرط <= 2)
        $a = $engine->start('leave-request', [], $employee->id, 'B-2');
        $engine->completeTask($engine->openTasksOf($a->id())[0]->id(), [
            'leave_type' => 'استحقاقی', 'days' => 2,
            'from_date' => '1405/08/10', 'to_date' => '1405/08/11',
        ], $employee->id, $engine->rolesOf($employee->id));
        self::assertSame(InstanceStatus::Completed, $this->reloadInstance($a->id())->status());

        // days=3 → manager (default)
        $b = $engine->start('leave-request', [], $employee->id, 'B-3');
        $engine->completeTask($engine->openTasksOf($b->id())[0]->id(), [
            'leave_type' => 'استحقاقی', 'days' => 3,
            'from_date' => '1405/08/10', 'to_date' => '1405/08/12',
        ], $employee->id, $engine->rolesOf($employee->id));
        $freshB = $this->reloadInstance($b->id());
        self::assertSame(InstanceStatus::Running, $freshB->status());
        self::assertSame('manager_approval', $freshB->currentState());

        // مسیر ۲ روز: auto_approve + notify اجرا شد؛ مسیر ۳ روز هنوز در انتظار مدیر است
        self::assertSame(['approveLeave', 'sendNotification'], $calls);
    }

    // ─── E2E-05: parallel gateway (fork/join) ─────────────────────────────

    public function testE2E05_ParallelGatewayForkJoin(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $finance = $this->user('finance@bpms.test', 'مالی', 'finance');
        $warehouse = $this->user('warehouse@bpms.test', 'انبار', 'warehouse');
        $calls = [];
        $this->registerLeaveActions($calls);
        $this->publishPurchaseDefinition();

        $engine = $this->engine();
        $instance = $engine->start('purchase-approval', [], $employee->id, 'PO-1');

        // ثبت درخواست → fork به دو کار موازی
        $request = $engine->openTasksOf($instance->id())[0];
        self::assertSame('request', $request->stateId());
        $engine->completeTask($request->id(), ['item' => 'سرور', 'amount' => 500_000_000], $employee->id, $engine->rolesOf($employee->id));

        $open = $engine->openTasksOf($instance->id());
        self::assertCount(2, $open);
        $states = [$open[0]->stateId(), $open[1]->stateId()];
        \sort($states);
        self::assertSame(['finance_review', 'warehouse_review'], $states);
        self::assertSame(2, \count($this->reloadInstance($instance->id())->tokens()));

        // تکمیل یکی از دو شاخه → join کامل نیست، فرایند جاری می‌ماند
        $financeTask = $open[0]->stateId() === 'finance_review' ? $open[0] : $open[1];
        $warehouseTask = $open[0]->stateId() === 'warehouse_review' ? $open[0] : $open[1];

        $engine->completeTask($financeTask->id(), ['finance_ok' => '1'], $finance->id, $engine->rolesOf($finance->id));
        $mid = $this->reloadInstance($instance->id());
        self::assertSame(InstanceStatus::Running, $mid->status());
        self::assertSame([], $calls);   // هنوز به service نرسیده

        // شاخهٔ دوم → join → gateway (هر دو ok) → approved → end
        $engine->completeTask($warehouseTask->id(), ['warehouse_ok' => '1'], $warehouse->id, $engine->rolesOf($warehouse->id));
        $final = $this->reloadInstance($instance->id());
        self::assertSame(InstanceStatus::Completed, $final->status());
        self::assertSame(true, $final->getVariable('finance_ok'));
        self::assertSame(true, $final->getVariable('warehouse_ok'));
        self::assertSame(['notify_initiator'], $calls);

        // history شامل fork و join
        $history = $this->app()->make(HistoryRepositoryInterface::class)->forInstance($instance->id());
        $transitions = \array_map(static fn ($h): string => $h->transition, $history);
        self::assertContains('fork:split', $transitions);
        self::assertContains('join:join', $transitions);
    }

    // ─── E2E-06: timer escalation (AC-BPMS-13) ────────────────────────────

    public function testE2E06_TimerEscalationIsIdempotent(): void
    {
        Clock::setTestNow('2026-10-26 08:00:00');

        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $this->user('padmin@bpms.test', 'مدیر فرایند', 'process-admin');
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();

        $escalated = [];
        Event::listen('bpms.task.escalated', static function (string $event, mixed $payload) use (&$escalated): void {
            $escalated[] = (array) $payload;
        });

        $engine = $this->engine();
        $instance = $engine->start('leave-request', [], $employee->id);
        $engine->completeTask($engine->openTasksOf($instance->id())[0]->id(), [
            'leave_type' => 'استحقاقی', 'days' => 5,
            'from_date' => '1405/09/01', 'to_date' => '1405/09/05',
        ], $employee->id, $engine->rolesOf($employee->id));

        $task = $engine->openTasksOf($instance->id())[0];
        self::assertSame('2026-10-28 08:00:00.000', $task->dueAt());   // P2D

        // پیش از سررسید → بی‌اثر
        $timers = $this->app()->make(TimerService::class);
        Clock::setTestNow('2026-10-27 08:00:00');
        self::assertSame(0, $timers->processDue());

        // پس از سررسید → escalate
        Clock::setTestNow('2026-10-29 08:00:01');
        self::assertSame(1, $timers->processDue());

        $fresh = $this->app()->make(TaskRepositoryInterface::class)->findById($task->id());
        self::assertNotNull($fresh);
        self::assertTrue($fresh->isEscalated());
        self::assertSame('role', $fresh->assigneeType()->value);
        self::assertSame('process-admin', $fresh->assigneeId());   // نقش escalation از config
        self::assertCount(1, $escalated);
        self::assertSame($task->id(), $escalated[0]['task_id']);

        // اعلان به نقش process-admin (database)
        self::assertTrue(DB::table('tvn_notifications')
            ->where('data', 'LIKE', '%bpms.task.escalated%')
            ->exists());

        // 🔒 idempotent — اجرای دوباره بی‌اثر
        self::assertSame(0, $timers->processDue());
        self::assertCount(1, $escalated);

        // کار escalateشده همچنان توسط مدیر جدید قابل انجام است
        $padmin = $this->users->findByEmail('padmin@bpms.test');
        self::assertNotNull($padmin);
        $engine->claim($fresh->id(), $padmin->id, $engine->rolesOf($padmin->id));
        $engine->completeTask($fresh->id(), ['approved' => '1'], $padmin->id, $engine->rolesOf($padmin->id));
        self::assertSame(InstanceStatus::Completed, $this->reloadInstance($instance->id())->status());
    }

    public function testE2E06b_TimerCustomActionFromRegistry(): void
    {
        Clock::setTestNow('2026-10-26 08:00:00');

        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $hits = 0;
        $registry = $this->app()->make(ActionRegistry::class);
        $registry->register('autoRemind', function (ProcessContext $ctx) use (&$hits): void {
            $hits++;
            $ctx->set('reminded', true);
        });
        $this->publishSimpleDefinitionWithTimer('autoRemind');

        $engine = $this->engine();
        $instance = $engine->start('timed-process', [], $employee->id);
        $task = $engine->openTasksOf($instance->id())[0];

        Clock::setTestNow('2026-10-29 08:00:00');
        self::assertSame(1, $this->app()->make(TimerService::class)->processDue());
        self::assertSame(1, $hits);

        // متغیر از راه context ست شد
        self::assertSame(true, $this->reloadInstance($instance->id())->getVariable('reminded'));

        // idempotent
        Clock::setTestNow('2026-10-30 08:00:00');
        self::assertSame(0, $this->app()->make(TimerService::class)->processDue());
        self::assertSame(1, $hits);

        // کار هنوز باز است (اکشن سفارشی reassign نمی‌کند)
        $fresh = $this->app()->make(TaskRepositoryInterface::class)->findById($task->id());
        self::assertNotNull($fresh);
        self::assertTrue($fresh->status()->isOpen());
        self::assertTrue($fresh->isEscalated());   // مُهر idempotency
    }

    // ─── E2E-07: فرم پویا + validation ────────────────────────────────────

    public function testE2E07_DynamicFormValidation(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();

        $engine = $this->engine();
        $instance = $engine->start('leave-request', [], $employee->id);
        $task = $engine->openTasksOf($instance->id())[0];

        // days خارج از بازه (max=30) → رد
        try {
            $engine->completeTask($task->id(), [
                'leave_type' => 'استحقاقی', 'days' => 99,
                'from_date' => '1405/09/01', 'to_date' => '1405/09/05',
            ], $employee->id, $engine->rolesOf($employee->id));
            self::fail('باید ValidationException می‌داد');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('days', $e->errors);
        }

        // فیلد required جاافتاده → رد
        try {
            $engine->completeTask($task->id(), ['days' => 3], $employee->id, $engine->rolesOf($employee->id));
            self::fail('باید ValidationException می‌داد');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('leave_type', $e->errors);
        }

        // تاریخ جلالی نامعتبر → رد
        try {
            $engine->completeTask($task->id(), [
                'leave_type' => 'استحقاقی', 'days' => 3,
                'from_date' => '2026-10-01', 'to_date' => '1405/09/05',
            ], $employee->id, $engine->rolesOf($employee->id));
            self::fail('باید ValidationException می‌داد');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('from_date', $e->errors);
        }

        // select خارج از گزینه‌ها → رد
        try {
            $engine->completeTask($task->id(), [
                'leave_type' => 'نامرئی', 'days' => 3,
                'from_date' => '1405/09/01', 'to_date' => '1405/09/05',
            ], $employee->id, $engine->rolesOf($employee->id));
            self::fail('باید ValidationException می‌داد');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('leave_type', $e->errors);
        }

        // 🔒 کلید خارج از schema هرگز در متغیرها ادغام نمی‌شود
        // دادهٔ نامعتبر → task باز و نمونه جاری می‌ماند (بدون اثر جانبی)
        $fresh = $this->reloadInstance($instance->id());
        self::assertSame(InstanceStatus::Running, $fresh->status());
        self::assertCount(1, $engine->openTasksOf($instance->id()));

        // دادهٔ معتبر → پذیرش + ادغام متغیرها
        $engine->completeTask($task->id(), [
            'leave_type' => 'استعلاجی', 'days' => 4,
            'from_date' => '1405/09/01', 'to_date' => '1405/09/04',
            'reason' => 'بستری', 'evil_extra' => 'x',
        ], $employee->id, $engine->rolesOf($employee->id));

        $final = $this->reloadInstance($instance->id());
        self::assertSame(4, $final->getVariable('days'));
        self::assertSame('استعلاجی', $final->getVariable('leave_type'));
        self::assertNull($final->getVariable('evil_extra'));
    }

    // ─── E2E-08: service task → اکشن ثبت‌شده ─────────────────────────────

    public function testE2E08_ServiceTaskRunsRegisteredAction(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');

        $seen = [];
        $registry = $this->app()->make(ActionRegistry::class);
        $registry->register('syncExternal', function (ProcessContext $ctx) use (&$seen): void {
            $seen[] = [
                'instance' => $ctx->instance()->id(),
                'key' => $ctx->instance()->definitionKey(),
                'order_id' => $ctx->get('order_id'),
                'actor' => $ctx->actorId(),
            ];
            $ctx->set('synced_at', '2026-10-26');
        });

        $designer = $this->app()->make(DesignerService::class);
        $draft = $designer->createDraft([
            'key' => 'sync-process',
            'name' => 'همگام‌سازی',
            'definition' => [
                'startEvent' => 'start',
                'elements' => [
                    ['id' => 'start', 'type' => 'startEvent', 'next' => 'sync'],
                    ['id' => 'sync', 'type' => 'serviceTask', 'action' => 'syncExternal', 'next' => 'end'],
                    ['id' => 'end', 'type' => 'endEvent'],
                ],
            ],
        ], $employee->id);
        $designer->publish($draft->id(), $employee->id);

        $engine = $this->engine();
        $instance = $engine->start('sync-process', ['order_id' => 42], $employee->id);

        // اکشن با context کامل اجرا شد
        self::assertCount(1, $seen);
        self::assertSame($instance->id(), $seen[0]['instance']);
        self::assertSame(42, $seen[0]['order_id']);
        self::assertSame(InstanceStatus::Completed, $this->reloadInstance($instance->id())->status());
        self::assertSame('2026-10-26', $this->reloadInstance($instance->id())->getVariable('synced_at'));

        // اکشن ثبت‌نشده → فرایند start نمی‌شود (خطای قطعی، بدون side effect)
        $draft2 = $designer->createDraft([
            'key' => 'bad-action',
            'name' => 'اکشن بد',
            'definition' => [
                'startEvent' => 'start',
                'elements' => [
                    ['id' => 'start', 'type' => 'startEvent', 'next' => 'x'],
                    ['id' => 'x', 'type' => 'serviceTask', 'action' => 'unknownEvil', 'next' => 'end'],
                    ['id' => 'end', 'type' => 'endEvent'],
                ],
            ],
        ], $employee->id);
        $designer->publish($draft2->id(), $employee->id);

        try {
            $engine->start('bad-action', [], $employee->id);
            self::fail('باید unknown_action می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('unknown_action', $e->reason());
        }
    }

    // ─── E2E-09: history + audit کامل ─────────────────────────────────────

    public function testE2E09_HistoryTrailIsComplete(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $manager = $this->user('manager@bpms.test', 'مدیر', 'manager');
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();

        $engine = $this->engine();
        $instance = $engine->start('leave-request', [], $employee->id);
        $engine->completeTask($engine->openTasksOf($instance->id())[0]->id(), [
            'leave_type' => 'استحقاقی', 'days' => 5,
            'from_date' => '1405/09/01', 'to_date' => '1405/09/05',
        ], $employee->id, $engine->rolesOf($employee->id));
        $task = $engine->openTasksOf($instance->id())[0];
        $engine->completeTask($task->id(), ['approved' => '1'], $manager->id, $engine->rolesOf($manager->id));

        $history = $this->app()->make(HistoryRepositoryInterface::class)->forInstance($instance->id());
        $steps = \array_map(static fn ($h): string => ($h->fromState ?? '∅') . '→' . $h->toState . ':' . $h->transition, $history);

        // مسیر کامل: start → submit → gateway → manager → gateway → notify → end
        self::assertContains('∅→start:start', $steps);
        self::assertContains('start→submit:start', $steps);
        self::assertContains('submit→check_days:task', $steps);
        self::assertContains('check_days→manager_approval:gateway:check_days', $steps);
        self::assertContains('manager_approval→decision:task', $steps);
        self::assertContains('decision→notify:gateway:decision', $steps);
        self::assertContains('notify→end:service:sendNotification', $steps);

        // actor کاربر روی گذارهای انسانی
        $taskMoves = \array_values(\array_filter($history, static fn ($h): bool => $h->trigger === 'user'));
        self::assertCount(2, $taskMoves);
        self::assertSame($employee->id, $taskMoves[0]->actorId);
        self::assertSame($manager->id, $taskMoves[1]->actorId);
        self::assertNotNull($taskMoves[0]->durationMs);   // زمان ماند در state

        // audit موتور — publish مدیریتی در زنجیره است
        self::assertTrue(DB::table('tvn_audit_logs')->where('event', 'definition.published')->exists());
    }

    // ─── E2E-10: expression evaluator امن (بدون eval) ─────────────────────

    public function testE2E10_ExpressionInjectionRejectedAtPublishAndRuntime(): void
    {
        $designer = $this->app()->make(DesignerService::class);

        // لایهٔ ۱ — publish با expr مخرب رد می‌شود (AC-BPMS-11/12)
        try {
            $draft = $designer->createDraft([
                'key' => 'evil-process',
                'name' => 'فرایند مخرب',
                'definition' => [
                    'startEvent' => 'start',
                    'elements' => [
                        ['id' => 'start', 'type' => 'startEvent', 'next' => 'gw'],
                        ['id' => 'gw', 'type' => 'exclusiveGateway', 'conditions' => [
                            ['expr' => 'system("rm -rf /")', 'next' => 'end'],
                            ['default' => true, 'next' => 'end'],
                        ]],
                        ['id' => 'end', 'type' => 'endEvent'],
                    ],
                ],
            ]);
            self::fail('createDraft باید expr مخرب را رد می‌کرد');
        } catch (BpmsException $e) {
            self::assertSame('invalid_definition', $e->reason());
        }

        // انواع دیگر تزریق هم رد می‌شوند
        foreach (['$this->x', 'phpinfo()', 'a; system("x")', '`ls`', 'variables.x == 1, 2'] as $evil) {
            try {
                $designer->createDraft([
                    'key' => 'evil-' . \substr(\md5($evil), 0, 8),
                    'name' => 'x',
                    'definition' => [
                        'startEvent' => 'start',
                        'elements' => [
                            ['id' => 'start', 'type' => 'startEvent', 'next' => 'gw'],
                            ['id' => 'gw', 'type' => 'exclusiveGateway', 'conditions' => [
                                ['expr' => $evil, 'next' => 'end'],
                                ['default' => true, 'next' => 'end'],
                            ]],
                            ['id' => 'end', 'type' => 'endEvent'],
                        ],
                    ],
                ]);
                self::fail('باید رد می‌شد: ' . $evil);
            } catch (BpmsException $e) {
                self::assertSame('invalid_definition', $e->reason());
            }
        }

        // عبارت معتبر پذیرفته می‌شود
        $ok = $designer->createDraft([
            'key' => 'safe-process',
            'name' => 'فرایند سالم',
            'definition' => [
                'startEvent' => 'start',
                'elements' => [
                    ['id' => 'start', 'type' => 'startEvent', 'next' => 'gw'],
                    ['id' => 'gw', 'type' => 'exclusiveGateway', 'conditions' => [
                        ['expr' => "variables.amount > 1000 && variables.kind == 'buy'", 'next' => 'end'],
                        ['default' => true, 'next' => 'end'],
                    ]],
                    ['id' => 'end', 'type' => 'endEvent'],
                ],
            ],
        ]);
        $designer->publish($ok->id());

        // لایهٔ ۲ — در اجرا هم expr با context واقعی ارزیابی می‌شود
        $engine = $this->engine();
        $instance = $engine->start('safe-process', ['amount' => 5000, 'kind' => 'buy']);
        self::assertSame(InstanceStatus::Completed, $this->reloadInstance($instance->id())->status());
    }

    // ─── mirror موتور workflow + سقف DoS ──────────────────────────────────

    public function testWorkflowMirrorIsRecordedAndAdvanced(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();

        // تعریف mirror در tvn_workflows ثبت شده
        self::assertTrue(DB::table('tvn_workflows')->where('name', 'bpms-leave-request')->exists());

        $engine = $this->engine();
        $instance = $engine->start('leave-request', [], $employee->id, 'MIRROR-1');
        self::assertNotNull($instance->workflowInstanceId());

        $mirror = DB::table('tvn_process_instances')->where('id', $instance->workflowInstanceId())->first();
        self::assertNotNull($mirror);
        self::assertSame('submit', $mirror['current_state']);

        // حرکت تا پایان → mirror هم completed می‌شود
        $engine->completeTask($engine->openTasksOf($instance->id())[0]->id(), [
            'leave_type' => 'استحقاقی', 'days' => 1,
            'from_date' => '1405/08/10', 'to_date' => '1405/08/10',
        ], $employee->id, $engine->rolesOf($employee->id));

        $mirror = DB::table('tvn_process_instances')->where('id', $instance->workflowInstanceId())->first();
        self::assertNotNull($mirror);
        self::assertSame('completed', $mirror['status']);

        // تاریخچهٔ mirror هم گام‌ها را دارد
        $mirrorHistory = DB::table('tvn_process_history')->where('instance_id', $instance->workflowInstanceId())->count();
        self::assertGreaterThan(3, $mirrorHistory);
    }

    public function testMaxActiveInstancesLimitEnforced(): void
    {
        $this->app()->make('config')->set('bpms.max_active_instances', 1);

        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();

        $engine = $this->engine();
        $engine->start('leave-request', [], $employee->id, 'L-1');

        try {
            $engine->start('leave-request', [], $employee->id, 'L-2');
            self::fail('باید too_many_instances می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('too_many_instances', $e->reason());
        }
    }

    public function testSuspendBlocksTaskCompletionAndTerminateCancelsTasks(): void
    {
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $this->registerLeaveActions($calls);
        $this->publishLeaveDefinition();

        $engine = $this->engine();
        $instance = $engine->start('leave-request', [], $employee->id);
        $task = $engine->openTasksOf($instance->id())[0];

        // suspend → تکمیل کار رد می‌شود
        $engine->suspend($instance->id(), 1);
        try {
            $engine->completeTask($task->id(), [
                'leave_type' => 'استحقاقی', 'days' => 1,
                'from_date' => '1405/08/10', 'to_date' => '1405/08/10',
            ], $employee->id, $engine->rolesOf($employee->id));
            self::fail('باید not_running می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('not_running', $e->reason());
        }

        // resume → دوباره جاری
        $engine->resume($instance->id(), 1);
        self::assertTrue($this->reloadInstance($instance->id())->isRunning());

        // terminate → کارهای باز لغو + نمونه terminated
        $terminated = null;
        Event::listen('bpms.process.terminated', static function (string $event, mixed $payload) use (&$terminated): void {
            $terminated = (array) $payload;
        });

        $engine->terminate($instance->id(), 1, 'duplicate');
        $fresh = $this->reloadInstance($instance->id());
        self::assertSame(InstanceStatus::Terminated, $fresh->status());
        self::assertCount(0, $engine->openTasksOf($instance->id()));
        self::assertNotNull($terminated);
        self::assertSame('duplicate', $terminated['reason']);

        $canceled = $this->app()->make(TaskRepositoryInterface::class)->findById($task->id());
        self::assertNotNull($canceled);
        self::assertSame(TaskStatus::Canceled, $canceled->status());
    }

    public function testGuestAndRoleGuardsOnWebRoutes(): void
    {
        // مهمان → redirect به login
        $response = $this->get('/bpms/tasks');
        self::assertSame(302, $response->status());

        // task-assignee بدون مجوز پنل → 403 روی صفحات ادمین
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $this->actingAs($employee);
        self::assertSame(403, $this->get('/admin/bpms/definitions')->status());
        self::assertSame(403, $this->get('/admin/bpms')->status());

        // inbox خودش را دارد
        self::assertSame(200, $this->get('/bpms/tasks')->status());
    }

    // ─── helpers ───────────────────────────────────────────────────────────

    private function engine(): ProcessEngine
    {
        return $this->app()->make(ProcessEngine::class);
    }

    private function reloadInstance(string $id): \Modules\Bpms\Domain\Entities\ProcessInstance
    {
        $instance = $this->app()->make(InstanceRepositoryInterface::class)->findById($id);
        self::assertNotNull($instance);

        return $instance;
    }

    /** اکشن‌های سناریوی مرخصی (§6) — ثبت روی registry مشترک app. */
    private function registerLeaveActions(?array &$calls): void
    {
        $calls = [];
        $registry = $this->app()->make(ActionRegistry::class);
        $registry->register('approveLeave', static function (ProcessContext $ctx) use (&$calls): void {
            $calls[] = 'approveLeave';
            $ctx->set('leave_approved', true);
        });
        $registry->register('sendNotification', static function (ProcessContext $ctx) use (&$calls): void {
            $calls[] = 'sendNotification';
        });
        $registry->register('sendRejection', static function (ProcessContext $ctx) use (&$calls): void {
            $calls[] = 'sendRejection';
        });
        $registry->register('notify_initiator', static function (ProcessContext $ctx) use (&$calls): void {
            $calls[] = 'notify_initiator';
        });
    }

    /** تعریف مرخصی — دقیقاً DOC-098 §3 + فرم §4 (publish + activate). */
    private function publishLeaveDefinition(): ProcessDefinition
    {
        $designer = $this->app()->make(DesignerService::class);

        $existing = $this->app()->make(DefinitionRepositoryInterface::class)->findActiveByKey('leave-request');
        if ($existing !== null) {
            return $existing;
        }

        $draft = $designer->createDraft([
            'key' => 'leave-request',
            'name' => 'درخواست مرخصی',
            'definition' => [
                'startEvent' => 'start',
                'elements' => [
                    ['id' => 'start', 'type' => 'startEvent', 'next' => 'submit'],
                    ['id' => 'submit', 'type' => 'userTask', 'name' => 'ثبت درخواست',
                     'assignee' => 'initiator', 'form' => 'leave_form', 'next' => 'check_days'],
                    ['id' => 'check_days', 'type' => 'exclusiveGateway', 'name' => 'بررسی تعداد روز',
                     'conditions' => [
                         ['expr' => 'variables.days <= 2', 'next' => 'auto_approve'],
                         ['default' => true, 'next' => 'manager_approval'],
                     ]],
                    ['id' => 'auto_approve', 'type' => 'serviceTask', 'name' => 'تأیید خودکار',
                     'action' => 'approveLeave', 'next' => 'notify'],
                    ['id' => 'manager_approval', 'type' => 'userTask', 'name' => 'تأیید مدیر',
                     'assignee' => 'role:manager', 'form' => 'approval_form', 'due' => 'P2D',
                     'priority' => 'high', 'next' => 'decision'],
                    ['id' => 'decision', 'type' => 'exclusiveGateway',
                     'conditions' => [
                         ['expr' => 'variables.approved', 'next' => 'notify'],
                         ['default' => true, 'next' => 'reject'],
                     ]],
                    ['id' => 'notify', 'type' => 'serviceTask', 'action' => 'sendNotification', 'next' => 'end'],
                    ['id' => 'reject', 'type' => 'serviceTask', 'action' => 'sendRejection', 'next' => 'end'],
                    ['id' => 'end', 'type' => 'endEvent'],
                ],
                'timers' => [
                    ['on' => 'manager_approval', 'after' => 'P2D', 'action' => 'escalate'],
                ],
            ],
            'form_schema' => [
                'leave_form' => [
                    'id' => 'leave_form',
                    'fields' => [
                        ['name' => 'leave_type', 'type' => 'select', 'label' => 'نوع مرخصی',
                         'options' => ['استحقاقی', 'استعلاجی', 'بدون حقوق'], 'required' => true],
                        ['name' => 'days', 'type' => 'number', 'label' => 'تعداد روز', 'min' => 1, 'max' => 30, 'required' => true],
                        ['name' => 'from_date', 'type' => 'jalali_date', 'label' => 'از تاریخ', 'required' => true],
                        ['name' => 'to_date', 'type' => 'jalali_date', 'label' => 'تا تاریخ', 'required' => true],
                        ['name' => 'reason', 'type' => 'textarea', 'label' => 'دلیل'],
                    ],
                ],
                'approval_form' => [
                    'id' => 'approval_form',
                    'fields' => [
                        ['name' => 'approved', 'type' => 'checkbox', 'label' => 'تأیید می‌شود'],
                        ['name' => 'manager_note', 'type' => 'textarea', 'label' => 'یادداشت مدیر'],
                    ],
                ],
            ],
        ]);

        return $designer->publish($draft->id());
    }

    /** تعریف خرید با parallel gateway (E2E-05). */
    private function publishPurchaseDefinition(): ProcessDefinition
    {
        $designer = $this->app()->make(DesignerService::class);

        $existing = $this->app()->make(DefinitionRepositoryInterface::class)->findActiveByKey('purchase-approval');
        if ($existing !== null) {
            return $existing;
        }

        $draft = $designer->createDraft([
            'key' => 'purchase-approval',
            'name' => 'تأیید خرید موازی',
            'definition' => [
                'startEvent' => 'start',
                'elements' => [
                    ['id' => 'start', 'type' => 'startEvent', 'next' => 'request'],
                    ['id' => 'request', 'type' => 'userTask', 'name' => 'ثبت درخواست خرید',
                     'assignee' => 'initiator', 'form' => 'purchase_form', 'next' => 'split'],
                    ['id' => 'split', 'type' => 'parallelGateway', 'fork' => ['finance_review', 'warehouse_review']],
                    ['id' => 'finance_review', 'type' => 'userTask', 'name' => 'بررسی مالی',
                     'assignee' => 'role:finance', 'form' => 'finance_form', 'next' => 'join'],
                    ['id' => 'warehouse_review', 'type' => 'userTask', 'name' => 'بررسی انبار',
                     'assignee' => 'role:warehouse', 'form' => 'warehouse_form', 'next' => 'join'],
                    ['id' => 'join', 'type' => 'parallelGateway', 'next' => 'decide'],
                    ['id' => 'decide', 'type' => 'exclusiveGateway', 'conditions' => [
                        ['expr' => 'variables.finance_ok && variables.warehouse_ok', 'next' => 'approved'],
                        ['default' => true, 'next' => 'rejected'],
                    ]],
                    ['id' => 'approved', 'type' => 'serviceTask', 'action' => 'notify_initiator', 'next' => 'end'],
                    ['id' => 'rejected', 'type' => 'serviceTask', 'action' => 'notify_initiator', 'next' => 'end'],
                    ['id' => 'end', 'type' => 'endEvent'],
                ],
            ],
            'form_schema' => [
                'purchase_form' => ['id' => 'purchase_form', 'fields' => [
                    ['name' => 'item', 'type' => 'text', 'label' => 'کالا', 'required' => true],
                    ['name' => 'amount', 'type' => 'number', 'label' => 'مبلغ', 'min' => 1, 'required' => true],
                ]],
                'review_form' => ['id' => 'review_form', 'fields' => [
                    ['name' => 'review_note', 'type' => 'textarea', 'label' => 'یادداشت'],
                ]],
                'finance_form' => ['id' => 'finance_form', 'fields' => [
                    ['name' => 'finance_ok', 'type' => 'checkbox', 'label' => 'تأیید مالی'],
                ]],
                'warehouse_form' => ['id' => 'warehouse_form', 'fields' => [
                    ['name' => 'warehouse_ok', 'type' => 'checkbox', 'label' => 'تأیید انبار'],
                ]],
            ],
        ]);

        return $designer->publish($draft->id());
    }

    /** تعریف ساده با تایمر سفارشی (E2E-06b). */
    private function publishSimpleDefinitionWithTimer(string $action): ProcessDefinition
    {
        $designer = $this->app()->make(DesignerService::class);

        $draft = $designer->createDraft([
            'key' => 'timed-process',
            'name' => 'فرایند زمان‌دار',
            'definition' => [
                'startEvent' => 'start',
                'elements' => [
                    ['id' => 'start', 'type' => 'startEvent', 'next' => 'review'],
                    ['id' => 'review', 'type' => 'userTask', 'name' => 'بررسی',
                     'assignee' => 'role:manager', 'due' => 'P2D', 'next' => 'end'],
                    ['id' => 'end', 'type' => 'endEvent'],
                ],
                'timers' => [
                    ['on' => 'review', 'after' => 'P2D', 'action' => $action],
                ],
            ],
        ]);

        return $designer->publish($draft->id());
    }

    private function seedRbac(): void
    {
        $permissions = [
            'bpms.definition.view', 'bpms.definition.manage', 'bpms.definition.publish',
            'bpms.instance.view', 'bpms.instance.view-all', 'bpms.instance.start',
            'bpms.instance.manage', 'bpms.instance.terminate',
            'bpms.task.view', 'bpms.task.view-all', 'bpms.task.claim', 'bpms.task.complete',
            'bpms.task.delegate', 'bpms.task.assign',
            'bpms.monitor.view', 'bpms.audit.view',
        ];
        foreach ($permissions as $key) {
            if (!DB::table('tvn_permissions')->where('key', $key)->exists()) {
                $this->roles->createPermission($key, 'tavaan.bpms');
            }
        }

        $taskPerms = ['bpms.task.view', 'bpms.task.claim', 'bpms.task.complete', 'bpms.task.delegate', 'bpms.instance.view'];

        $rolePerms = [
            'process-designer' => ['bpms.definition.view', 'bpms.definition.manage', 'bpms.definition.publish', 'bpms.instance.view'],
            'process-admin' => $permissions,
            'task-assignee' => $taskPerms,
            'monitor' => ['bpms.monitor.view', 'bpms.definition.view', 'bpms.instance.view', 'bpms.instance.view-all', 'bpms.task.view-all'],
            'auditor' => ['bpms.audit.view', 'bpms.monitor.view', 'bpms.instance.view', 'bpms.instance.view-all', 'bpms.task.view-all', 'bpms.definition.view'],
            // نقش‌های کسب‌وکاری سناریوها
            'manager' => $taskPerms,
            'finance' => $taskPerms,
            'warehouse' => $taskPerms,
            'admin' => $permissions,
        ];

        foreach ($rolePerms as $roleKey => $perms) {
            if ($this->roles->findRole($roleKey) === null) {
                $this->roles->create($roleKey, $roleKey);
            }
            foreach ($perms as $permission) {
                $this->roles->grantPermissionToRole($roleKey, $permission);
            }
        }
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }
}
