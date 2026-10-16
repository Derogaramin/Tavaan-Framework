<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Tests\Feature;

require_once __DIR__ . '/../Fixtures/WorkflowFixtures.php';

use Tavaan\Core\Application\Application;
use Tavaan\Core\Exceptions\Authorization\AuthorizationException;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Engine\Workflow\Duration;
use Tavaan\Engine\Workflow\Events\WorkflowCompleted;
use Tavaan\Engine\Workflow\Exceptions\IllegalTransitionException;
use Tavaan\Engine\Workflow\Exceptions\ProcessDefinitionException;
use Tavaan\Engine\Workflow\Exceptions\TaskException;
use Tavaan\Engine\Workflow\Exceptions\WorkflowException;
use Tavaan\Engine\Workflow\ProcessBuilder;
use Tavaan\Engine\Workflow\ProcessContext;
use Tavaan\Engine\Workflow\StateType;
use Tavaan\Engine\Workflow\Store\ArrayStore;
use Tavaan\Engine\Workflow\Store\StoreInterface;
use Tavaan\Engine\Workflow\Tests\Fixtures\WfAuth;
use Tavaan\Engine\Workflow\Tests\Fixtures\WfUser;
use Tavaan\Engine\Workflow\TimerProcessor;
use Tavaan\Engine\Workflow\Visualizer;
use Tavaan\Engine\Workflow\WorkflowManager;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Workflow — DOC-071 §9 (T-01..T-10) + §10 (AC-WF-11).
 */
final class WorkflowTest extends TestCase
{
    private Application $app;

    private WorkflowManager $workflows;

    private WfAuth $auth;

    /** @var \Tavaan\Core\Contracts\Events\DispatcherInterface */
    private mixed $events;

    protected function setUp(): void
    {
        Clock::setTestNow(null);

        $this->app = \tavaan_test_app();
        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $config->set('workflow.driver', 'database');

        $this->migrate();

        $this->auth = new WfAuth(null);
        // 'auth' یک alias است → instance باید روی کلید واقعی (AuthManager) بنشیند
        $this->app->instance(\Tavaan\Engine\Auth\AuthManager::class, $this->auth);

        $this->events = $this->app->make('events');
        $this->workflows = $this->app->make('workflow');
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    /** اجرای migrationهای واقعی (idempotent — کلاس anonymous یک‌بار declare می‌شود). */
    private function migrate(): void
    {
        static $migrations = null;
        if ($migrations === null) {
            $migrations = [
                require \base_path('engines/workflow/migrations/2026_10_17_000001_create_workflow_tables.php'),
                require \base_path('engines/rbac/migrations/2026_01_03_000001_create_rbac_tables.php'),
            ];
        }
        foreach ($migrations as $migration) {
            $migration->up();
        }
    }

    /** فرایند نمونهٔ DOC-071 §3 (ساده‌شده). */
    private function defineOrderApproval(): void
    {
        $this->workflows->define('order-approval', function (ProcessBuilder $p): void {
            $p->start('draft');
            $p->state('pending_review', StateType::UserTask)
                ->assignee('role:shop-manager')
                ->form('admin/orders/review');
            $p->state('manager_review', StateType::UserTask)->assignee('role:manager');
            $p->state('approved');
            $p->state('rejected', StateType::End);
            $p->state('completed', StateType::End);
            $p->exclusiveGateway('check_amount');

            $p->transition('submit', from: 'draft', to: 'pending_review');
            $p->transition('review', from: 'pending_review', to: 'check_amount')
                ->guard(fn (ProcessContext $c): bool => $c->can('shop.order.review'));
            $p->transition('approve', from: 'check_amount', to: 'approved')
                ->guard(fn (ProcessContext $c): bool => (int) $c->get('amount', 0) < 10_000_000);
            $p->transition('escalate', from: 'check_amount', to: 'manager_review')
                ->guard(fn (ProcessContext $c): bool => (int) $c->get('amount', 0) >= 10_000_000);
            $p->transition('reject', from: 'pending_review', to: 'rejected');
            $p->transition('finalize', from: 'approved', to: 'completed');

            $p->timerEvent('auto_reject', after: '3 days', on: 'pending_review', to: 'rejected');
        });
    }

    /** فرایند موازی fork/join (DOC-071 §3). */
    private function defineParallelShipping(): void
    {
        $this->workflows->define('parallel-ship', function (ProcessBuilder $p): void {
            $p->start('approved');
            $p->parallelGateway('split');
            $p->state('packing');
            $p->state('invoicing');
            $p->parallelGateway('join');
            $p->state('shipped');
            $p->state('done', StateType::End);

            $p->transition('start_parallel', from: 'approved', to: 'split');
            $p->transition('to_packing', from: 'split', to: 'packing');
            $p->transition('to_invoice', from: 'split', to: 'invoicing');
            $p->transition('pack_done', from: 'packing', to: 'join');
            $p->transition('invoice_done', from: 'invoicing', to: 'join');
            $p->transition('after_join', from: 'join', to: 'shipped');
            $p->transition('finish', from: 'shipped', to: 'done');
        });
    }

    private function login(int $userId, array $permissions = []): WfUser
    {
        $user = new WfUser($userId, $permissions);
        $this->auth->setUser($user);

        return $user;
    }

    // ─── T-01: تعریف فرایند + validation ───────────────────────────────────

    public function testDefineProcessWithDsl(): void
    {
        $this->defineOrderApproval();

        $definition = $this->workflows->definition('order-approval');
        self::assertSame('order-approval', $definition->id);
        self::assertSame(1, $definition->version);
        self::assertSame('draft', $definition->startStateId());
        self::assertTrue($definition->isGateway('check_amount'));
        self::assertTrue($definition->isEnd('rejected'));
        self::assertTrue($definition->isUserTask('pending_review'));
        self::assertCount(1, $definition->timers());
        self::assertCount(2, $definition->outgoing('pending_review'));

        // persist در DB
        $rows = $this->workflows->definitions();
        self::assertCount(1, $rows);
        self::assertSame('order-approval', (string) $rows[0]['name']);
    }

    public function testRedefineBumpsVersion(): void
    {
        $this->workflows->define('simple', function (ProcessBuilder $p): void {
            $p->start('a');
            $p->state('b', StateType::End);
            $p->transition('go', from: 'a', to: 'b');
        });
        $this->workflows->define('simple', function (ProcessBuilder $p): void {
            $p->start('a');
            $p->state('b', StateType::End);
            $p->transition('go', from: 'a', to: 'b');
        });

        self::assertSame(2, $this->workflows->definition('simple')->version);
    }

    public function testDefinitionValidationErrors(): void
    {
        // بدون start
        try {
            $this->workflows->defineArray(['id' => 'bad', 'states' => [['id' => 'x']], 'transitions' => []]);
            self::fail('Expected missing_start');
        } catch (ProcessDefinitionException $e) {
            self::assertSame('workflow.definition.missing_start', $e->messageId);
        }

        // گذار به state ناشناخته
        try {
            $this->workflows->defineArray([
                'id' => 'bad2',
                'states' => [['id' => 's', 'type' => 'start'], ['id' => 'e', 'type' => 'end']],
                'transitions' => [['id' => 't', 'from' => 's', 'to' => 'ghost']],
            ]);
            self::fail('Expected unknown_state');
        } catch (ProcessDefinitionException $e) {
            self::assertSame('workflow.definition.unknown_state', $e->messageId);
        }

        // state تکراری
        try {
            $this->workflows->defineArray([
                'id' => 'bad3',
                'states' => [['id' => 's', 'type' => 'start'], ['id' => 's']],
                'transitions' => [],
            ]);
            self::fail('Expected duplicate_state');
        } catch (ProcessDefinitionException $e) {
            self::assertSame('workflow.definition.duplicate_state', $e->messageId);
        }

        // state تنها (بدون یال)
        try {
            $this->workflows->defineArray([
                'id' => 'bad4',
                'states' => [['id' => 's', 'type' => 'start'], ['id' => 'lonely'], ['id' => 'e', 'type' => 'end']],
                'transitions' => [['id' => 't', 'from' => 's', 'to' => 'e']],
            ]);
            self::fail('Expected orphan_state');
        } catch (ProcessDefinitionException $e) {
            self::assertSame('workflow.definition.orphan_state', $e->messageId);
        }
    }

    // ─── T-02: fire با guard رد → IllegalTransition ────────────────────────

    public function testFireWithFailingGuardThrowsAndKeepsState(): void
    {
        $this->defineOrderApproval();
        $this->login(1);   // بدون مجوز review

        $instance = $this->workflows->start('order-approval', 'order', '42', ['amount' => 5_000_000]);
        $instance->fire('submit');
        self::assertSame('pending_review', $instance->currentState());

        $this->expectException(IllegalTransitionException::class);
        $this->expectExceptionMessageId('workflow.illegal_transition');
        try {
            $instance->fire('review');
        } finally {
            // 🔒 اتمیک: state پس از شکست تغییر نکرده
            $reloaded = $this->workflows->instance($instance->id);
            self::assertSame('pending_review', $reloaded->currentState());
        }
    }

    public function testFireUnknownTransitionThrows(): void
    {
        $this->defineOrderApproval();
        $instance = $this->workflows->start('order-approval');

        $this->expectException(IllegalTransitionException::class);
        $this->expectExceptionMessageId('workflow.unknown_transition');
        $instance->fire('teleport');
    }

    public function testFireWithoutTokenInSourceStateThrows(): void
    {
        $this->defineOrderApproval();
        $instance = $this->workflows->start('order-approval');

        $this->expectException(IllegalTransitionException::class);
        $this->expectExceptionMessageId('workflow.illegal_transition');
        $instance->fire('reject');   // توکن در draft است نه pending_review
    }

    // ─── T-03: دروازهٔ انحصاری (branch درست) ──────────────────────────────

    public function testExclusiveGatewayTakesLowAmountBranch(): void
    {
        $this->defineOrderApproval();
        $this->login(7, ['shop.order.review']);

        $instance = $this->workflows->start('order-approval', 'order', '1', ['amount' => 5_000_000]);
        $instance->fire('submit');
        $instance->fire('review');

        self::assertSame('approved', $instance->currentState());

        $history = $instance->history();
        $transitions = \array_column($history, 'transition');
        self::assertContains('approve', $transitions);
        self::assertTrue(!\in_array('escalate', $transitions, true));
    }

    public function testExclusiveGatewayTakesEscalationBranch(): void
    {
        $this->defineOrderApproval();
        $this->login(7, ['shop.order.review']);

        $instance = $this->workflows->start('order-approval', 'order', '2', ['amount' => 15_000_000]);
        $instance->fire('submit');
        $instance->fire('review');

        self::assertSame('manager_review', $instance->currentState());
    }

    // ─── T-04: Parallel fork/join ─────────────────────────────────────────

    public function testParallelForkCreatesBothTokens(): void
    {
        $this->defineParallelShipping();
        $instance = $this->workflows->start('parallel-ship');
        $instance->fire('start_parallel');

        $states = $instance->states();
        self::assertCount(2, $states);
        self::assertContains('packing', $states);
        self::assertContains('invoicing', $states);
    }

    public function testParallelJoinWaitsForAllTokens(): void
    {
        $this->defineParallelShipping();
        $instance = $this->workflows->start('parallel-ship');
        $instance->fire('start_parallel');

        $instance->fire('pack_done');
        // یک توکن در join منتظر است + invoicing هنوز فعال
        $states = $instance->states();
        self::assertContains('invoicing', $states);
        self::assertContains('join', $states);
        self::assertFalse($instance->isCompleted());

        $instance->fire('invoice_done');
        // join آزاد شد → shipped
        self::assertSame(['shipped'], $instance->states());

        $instance->fire('finish');
        self::assertTrue($instance->isCompleted());
        self::assertSame('done', $instance->currentState());
    }

    public function testManualFireFromGatewayRejected(): void
    {
        $this->defineParallelShipping();
        $instance = $this->workflows->start('parallel-ship');
        $instance->fire('start_parallel');
        $instance->fire('pack_done');   // توکن منتظر در join

        $this->expectException(IllegalTransitionException::class);
        $this->expectExceptionMessageId('workflow.gateway_manual_fire');
        $instance->fire('after_join');
    }

    // ─── T-05: User task + assignee + claim ───────────────────────────────

    public function testUserTaskCreatedWithRoleAssignee(): void
    {
        $this->defineOrderApproval();

        $seen = [];
        $this->events->listen('workflow.task.created', static function (string $name, mixed $payload) use (&$seen): void {
            $seen[] = $payload;
        });

        $instance = $this->workflows->start('order-approval');
        $instance->fire('submit');

        $tasks = $instance->tasks('pending');
        self::assertCount(1, $tasks);
        $task = $tasks[0];
        self::assertSame('pending_review', $task->stateId);
        self::assertSame('role', $task->assigneeType());
        self::assertSame('shop-manager', $task->assigneeId());
        self::assertCount(1, $seen);
    }

    public function testTaskClaimCompleteAndDelegate(): void
    {
        $this->defineOrderApproval();

        // نقش از راه RBAC واقعی (tvn_roles/tvn_role_user)
        /** @var RoleManager $roles */
        $roles = $this->app->make(RoleManager::class);
        $roles->create('shop-manager', 'Shop Manager');
        $roles->assign(7, 'shop-manager');

        $this->login(7, ['shop.order.review']);

        $instance = $this->workflows->start('order-approval');
        $instance->fire('submit');

        $tasks = $this->workflows->tasksFor(7);
        self::assertCount(1, $tasks);
        $task = $tasks[0];

        // claim با کاربر بدون نقش → رد
        try {
            $task->claim(9);
            self::fail('Expected not claimable');
        } catch (TaskException $e) {
            self::assertSame('workflow.task_not_claimable', $e->messageId);
        }

        // claim با کاربر دارای نقش
        $task->claim(7);
        self::assertSame('claimed', $this->workflows->task($task->id)->status());

        // delegate
        $task->delegate(9);
        $delegated = $this->workflows->task($task->id);
        self::assertSame('user', $delegated->assigneeType());
        self::assertSame('9', $delegated->assigneeId());
        self::assertCount(1, $this->workflows->tasksFor(9));

        // complete با transition صریح (دو گذار خروجی → ambiguous بدون آن)
        $this->auth->setUser(new WfUser(9, ['shop.order.review']));
        $result = $delegated->complete(['decision' => 'approved'], 'review');
        self::assertSame('approved', $result->currentState());
        self::assertSame('completed', $this->workflows->task($task->id)->status());
    }

    public function testTaskCompleteAmbiguousWithoutTransition(): void
    {
        $this->defineOrderApproval();
        $instance = $this->workflows->start('order-approval');
        $instance->fire('submit');
        $task = $instance->tasks('pending')[0];

        $this->expectException(TaskException::class);
        $this->expectExceptionMessageId('workflow.task_ambiguous_outgoing');
        $task->complete(['decision' => 'ok']);
    }

    public function testTaskCompleteViaInputTransitionKey(): void
    {
        $this->defineOrderApproval();
        $instance = $this->workflows->start('order-approval');
        $instance->fire('submit');
        $task = $instance->tasks('pending')[0];

        $result = $task->complete(['transition' => 'reject']);
        self::assertTrue($result->isCompleted());
        self::assertSame('rejected', $result->currentState());
    }

    // ─── T-06: Timer ───────────────────────────────────────────────────────

    public function testTimerFiresAfterDuration(): void
    {
        Clock::setTestNow('2026-10-17 10:00:00');
        $this->defineOrderApproval();

        $instance = $this->workflows->start('order-approval');
        $instance->fire('submit');
        self::assertSame('pending_review', $instance->currentState());
        self::assertCount(1, $instance->pendingTimers());

        $processor = $this->workflows->timerProcessor();

        // دو روز بعد — هنوز سررس نرسیده
        Clock::setTestNow('2026-10-19 10:00:00');
        self::assertSame(0, $processor->process());

        // چهار روز بعد — سررسیده
        Clock::setTestNow('2026-10-21 10:00:00');
        self::assertSame(1, $processor->process());

        $reloaded = $this->workflows->instance($instance->id);
        self::assertTrue($reloaded->isCompleted());
        self::assertSame('rejected', $reloaded->currentState());
    }

    public function testTimerCanceledWhenStateLeft(): void
    {
        Clock::setTestNow('2026-10-17 10:00:00');
        $this->defineOrderApproval();
        $this->login(7, ['shop.order.review']);

        $instance = $this->workflows->start('order-approval', null, null, ['amount' => 100]);
        $instance->fire('submit');
        $instance->fire('review');   // ترک pending_review → لغو auto_reject

        Clock::setTestNow('2026-10-30 10:00:00');
        self::assertSame(0, $this->workflows->timerProcessor()->process());
        self::assertSame('approved', $this->workflows->instance($instance->id)->currentState());
    }

    public function testDurationParsesHumanAndIso(): void
    {
        $base = new \DateTimeImmutable('2026-10-17 10:00:00');

        self::assertSame('2026-10-20 10:00:00', Duration::add($base, '3 days')->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-20 10:00:00', Duration::add($base, 'P3D')->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-17 14:00:00', Duration::add($base, 'PT4H')->format('Y-m-d H:i:s'));

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessageId('workflow.invalid_duration');
        Duration::parse('!invalid!');
    }

    // ─── T-07: History ─────────────────────────────────────────────────────

    public function testHistoryRecordedInOrder(): void
    {
        $this->defineOrderApproval();
        $this->login(7, ['shop.order.review']);

        $instance = $this->workflows->start('order-approval', 'order', '5', ['amount' => 100]);
        $instance->fire('submit');
        $instance->fire('review');

        $history = $instance->history();
        self::assertCount(3, $history);   // submit + review + approve (خودکار از دروازه)

        self::assertSame('draft', (string) $history[0]['from_state']);
        self::assertSame('pending_review', (string) $history[0]['to_state']);
        self::assertSame('submit', (string) $history[0]['transition']);
        self::assertSame(7, (int) $history[0]['user_id']);

        self::assertSame('check_amount', (string) $history[2]['from_state']);
        self::assertSame('approved', (string) $history[2]['to_state']);
        self::assertSame('approve', (string) $history[2]['transition']);
    }

    // ─── T-08: مجوز RBAC روی transition ───────────────────────────────────

    public function testTransitionPermissionEnforced(): void
    {
        $this->workflows->define('perm-flow', function (ProcessBuilder $p): void {
            $p->start('a');
            $p->state('b', StateType::End);
            $p->transition('go', from: 'a', to: 'b')->permission('shop.order.review');
        });

        $instance = $this->workflows->start('perm-flow');

        // بدون کاربر → unauthorized
        try {
            $instance->fire('go');
            self::fail('Expected unauthorized (no user)');
        } catch (AuthorizationException $e) {
            self::assertSame('workflow.unauthorized', $e->messageId);
        }

        // کاربر بدون مجوز → unauthorized
        $this->login(3);
        try {
            $instance->fire('go');
            self::fail('Expected unauthorized (no permission)');
        } catch (AuthorizationException $e) {
            self::assertSame('workflow.unauthorized', $e->messageId);
        }

        // کاربر با مجوز → OK
        $this->login(3, ['shop.order.review']);
        $instance->fire('go');
        self::assertTrue($instance->isCompleted());

        // can() بدون side effect
        $instance2 = $this->workflows->start('perm-flow');
        $this->login(5);   // بدون مجوز
        self::assertFalse($instance2->can('go'));
        $this->login(5, ['shop.order.review']);
        self::assertTrue($instance2->can('go'));
        self::assertSame(['go'], $instance2->availableTransitions());
    }

    // ─── T-09: Sub-process ─────────────────────────────────────────────────

    public function testSubProcessAdvancesParentOnChildCompletion(): void
    {
        $this->workflows->define('audit-flow', function (ProcessBuilder $p): void {
            $p->start('audit_start');
            $p->state('audit_end', StateType::End);
            $p->transition('do_audit', from: 'audit_start', to: 'audit_end');
        });

        $this->workflows->define('main-flow', function (ProcessBuilder $p): void {
            $p->start('init');
            $p->state('audit', StateType::SubProcess, process: 'audit-flow');
            $p->state('done', StateType::End);
            $p->transition('begin', from: 'init', to: 'audit');
            $p->transition('wrap_up', from: 'audit', to: 'done');
        });

        $parent = $this->workflows->start('main-flow', 'order', '9');
        $parent->fire('begin');
        self::assertSame('audit', $parent->currentState());

        $children = $parent->children();
        self::assertCount(1, $children);

        $child = $this->workflows->instance($children[0]);
        self::assertSame('audit_start', $child->currentState());
        self::assertSame(['instance' => $parent->id, 'state' => 'audit'], $child->parent());

        // تکمیل فرزند → والد خودکار از wrap_up عبور می‌کند
        $child->fire('do_audit');
        self::assertTrue($child->isCompleted());

        $reloadedParent = $this->workflows->instance($parent->id);
        self::assertTrue($reloadedParent->isCompleted());
        self::assertSame('done', $reloadedParent->currentState());
    }

    // ─── T-10: JSON definition load ───────────────────────────────────────

    public function testJsonDefinitionWithStringGuardAndTimer(): void
    {
        $json = <<<'JSON'
        {
          "id": "json-approval",
          "version": 1,
          "states": [
            {"id": "draft", "type": "start"},
            {"id": "pending_review", "type": "user_task", "assignee": "role:shop-manager"},
            {"id": "approved", "type": "normal"},
            {"id": "rejected", "type": "end"}
          ],
          "transitions": [
            {"id": "submit", "from": "draft", "to": "pending_review"},
            {"id": "review", "from": "pending_review", "to": "approved", "guard": "can:shop.order.review"}
          ],
          "timers": [
            {"id": "auto_reject", "after": "P3D", "on": "pending_review", "to": "rejected"}
          ]
        }
        JSON;

        $definition = $this->workflows->defineJson($json);
        self::assertSame('json-approval', $definition->id);

        $instance = $this->workflows->start('json-approval');
        $instance->fire('submit');

        // guard رشته‌ای can: — بدون کاربر → رد
        $this->login(1);
        try {
            $instance->fire('review');
            self::fail('Expected guard failure');
        } catch (IllegalTransitionException $e) {
            self::assertSame('workflow.illegal_transition', $e->messageId);
        }

        // با مجوز → قبول
        $this->login(1, ['shop.order.review']);
        $instance->fire('review');
        self::assertSame('approved', $instance->currentState());

        // تایمر ISO ثبت شده بود (و با ترک state لغو شد)
        self::assertCount(0, $instance->pendingTimers());
    }

    // ─── AC-WF-11: Visualizer ─────────────────────────────────────────────

    public function testVisualizerProducesDotAndMermaid(): void
    {
        $this->defineOrderApproval();
        $definition = $this->workflows->definition('order-approval');
        $visualizer = new Visualizer();

        $dot = $visualizer->toDot($definition);
        self::assertStringContainsString('digraph "order-approval"', $dot);
        self::assertStringContainsString('"draft" [shape=circle', $dot);
        self::assertStringContainsString('"rejected" [shape=doublecircle', $dot);
        self::assertStringContainsString('"check_amount" [shape=diamond', $dot);
        self::assertStringContainsString('"draft" -> "pending_review" [label="submit"]', $dot);
        self::assertStringContainsString('style=dashed', $dot);   // timer

        $mermaid = $visualizer->toMermaid($definition);
        self::assertStringContainsString('flowchart TD', $mermaid);
        self::assertStringContainsString('draft(("draft"))', $mermaid);
        self::assertStringContainsString('check_amount{check_amount}', $mermaid);
        self::assertStringContainsString('-.->|auto_reject', $mermaid);
    }

    // ─── رویدادها ─────────────────────────────────────────────────────────

    public function testLifecycleEventsDispatched(): void
    {
        $this->workflows->define('tiny', function (ProcessBuilder $p): void {
            $p->start('a');
            $p->state('b', StateType::End);
            $p->transition('go', from: 'a', to: 'b');
        });

        $strings = [];
        $objects = [];
        $this->events->listen('workflow.started', static function (string $n, mixed $p) use (&$strings): void {
            $strings[] = $n;
        });
        $this->events->listen('workflow.transitioned', static function (string $n, mixed $p) use (&$strings): void {
            $strings[] = $n;
        });
        $this->events->listen('workflow.completed', static function (string $n, mixed $p) use (&$strings): void {
            $strings[] = $n;
        });
        $this->events->listen(WorkflowCompleted::class, static function (object $e) use (&$objects): void {
            $objects[] = $e;
        });

        $instance = $this->workflows->start('tiny');
        $instance->fire('go');

        self::assertSame(['workflow.started', 'workflow.transitioned', 'workflow.completed'], $strings);
        self::assertCount(1, $objects);
        self::assertSame($instance->id, $objects[0]->instanceId);
    }

    // ─── امنیت/محافظ‌ها ───────────────────────────────────────────────────

    public function testMaxActiveInstancesGuard(): void
    {
        /** @var StoreInterface $store */
        $store = $this->app->make(StoreInterface::class);
        $manager = new WorkflowManager($store, ['max_active_instances' => 1], null, $this->app);
        $manager->define('limited', function (ProcessBuilder $p): void {
            $p->start('a');
            $p->state('b', StateType::End);
            $p->transition('go', from: 'a', to: 'b');
        });

        $manager->start('limited');

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessageId('workflow.max_instances');
        $manager->start('limited');
    }

    public function testSensitiveVariablesMaskedForDisplay(): void
    {
        $masked = $this->workflows->maskVariables([
            'amount' => 5000,
            'password' => 'hunter2',
            'card_number' => '6037991234567890',
        ]);

        self::assertSame(5000, $masked['amount']);
        self::assertSame('***', $masked['password']);
        self::assertSame('***', $masked['card_number']);
    }

    public function testReservedVariableKeysRejected(): void
    {
        $this->defineOrderApproval();
        $instance = $this->workflows->start('order-approval');

        $this->expectException(\InvalidArgumentException::class);
        $instance->setVariable('_tokens', ['hacked']);
    }

    public function testDirectStateManipulationOnlyViaFire(): void
    {
        $this->defineOrderApproval();
        $instance = $this->workflows->start('order-approval', null, null, ['amount' => 100]);

        // fire روی state تکمیل‌شده → not_running
        $instance->fire('submit');
        $task = $instance->tasks('pending')[0];
        $task->complete(['transition' => 'reject']);

        $reloaded = $this->workflows->instance($instance->id);
        self::assertTrue($reloaded->isCanceled() === false);
        self::assertTrue($reloaded->isCompleted());

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessageId('workflow.not_running');
        $reloaded->fire('submit');
    }

    public function testCancelStopsProcess(): void
    {
        $this->defineOrderApproval();
        $instance = $this->workflows->start('order-approval');
        $instance->cancel();

        self::assertTrue($instance->isCanceled());
        self::assertSame([], $instance->states());

        $reloaded = $this->workflows->instance($instance->id);
        self::assertSame('canceled', $reloaded->status());
    }

    // ─── ذخیره‌سازی ────────────────────────────────────────────────────────

    public function testVariablesPersistAndReload(): void
    {
        $this->defineOrderApproval();
        $instance = $this->workflows->start('order-approval', 'order', '77', ['amount' => 123]);
        $instance->setVariable('note', 'سلام');

        $reloaded = $this->workflows->instance($instance->id);
        self::assertSame(123, $reloaded->getVariable('amount'));
        self::assertSame('سلام', $reloaded->getVariable('note'));
        self::assertSame('order', $reloaded->subjectType());
        self::assertSame('77', $reloaded->subjectId());
        self::assertArrayNotHasKey('_tokens', $reloaded->variables());
    }

    public function testArrayDriverWorksWithoutDatabase(): void
    {
        $manager = new WorkflowManager(new ArrayStore(), [], $this->events, $this->app);
        $manager->define('mem-flow', function (ProcessBuilder $p): void {
            $p->start('a');
            $p->state('b');
            $p->state('c', StateType::End);
            $p->transition('go1', from: 'a', to: 'b');
            $p->transition('go2', from: 'b', to: 'c');
        });

        $instance = $manager->start('mem-flow');
        $instance->fire('go1');
        self::assertSame('b', $instance->currentState());

        // rehydrate از ArrayStore
        $reloaded = $manager->instance($instance->id);
        $reloaded->fire('go2');
        self::assertTrue($reloaded->isCompleted());
    }

    public function testTimerProcessorSurvivesBrokenInstance(): void
    {
        Clock::setTestNow('2026-10-17 10:00:00');
        $this->defineOrderApproval();
        $instance = $this->workflows->start('order-approval');
        $instance->fire('submit');

        Clock::setTestNow('2026-10-25 10:00:00');
        $processor = new TimerProcessor($this->workflows, 10);
        self::assertSame(1, $processor->process());

        // اجرای مجدد — تایمری باقی نمانده
        self::assertSame(0, $processor->process());
    }

    public function testNotifyAssigneeDispatchesEvent(): void
    {
        $this->defineOrderApproval();
        $seen = [];
        $this->events->listen('workflow.task.notify', static function (string $n, mixed $p) use (&$seen): void {
            $seen[] = $p;
        });

        $instance = $this->workflows->start('order-approval');
        $instance->fire('submit');
        $instance->notifyAssignee();

        self::assertCount(1, $seen);
        self::assertSame('pending_review', $seen[0]['state']);
        self::assertSame('role', $seen[0]['assignee_type']);
    }

    public function testActionRunsAndCanMutateVariables(): void
    {
        $this->workflows->define('action-flow', function (ProcessBuilder $p): void {
            $p->start('a');
            $p->state('b', StateType::End);
            $p->transition('go', from: 'a', to: 'b')
                ->action(static function (ProcessContext $c): void {
                    $c->set('stamped', true);
                    $c->set('total', (int) $c->get('amount', 0) * 2);
                });
        });

        $instance = $this->workflows->start('action-flow', null, null, ['amount' => 21]);
        $instance->fire('go');

        $reloaded = $this->workflows->instance($instance->id);
        self::assertTrue((bool) $reloaded->getVariable('stamped'));
        self::assertSame(42, $reloaded->getVariable('total'));
    }

    public function testFireInputMergedIntoVariables(): void
    {
        $this->defineOrderApproval();
        $this->login(7, ['shop.order.review']);

        $instance = $this->workflows->start('order-approval');
        $instance->fire('submit', ['comment' => 'لطفاً بررسی شود']);
        self::assertSame('لطفاً بررسی شود', $instance->getVariable('comment'));

        $reloaded = $this->workflows->instance($instance->id);
        self::assertSame('لطفاً بررسی شود', $reloaded->getVariable('comment'));
    }

    public function testStaleHandleSyncsWithStoreOnFire(): void
    {
        $this->workflows->define('stale-flow', function (ProcessBuilder $p): void {
            $p->start('init');
            $p->state('review', StateType::UserTask)->assignee('user:5');
            $p->state('done', StateType::End);
            $p->transition('begin', from: 'init', to: 'review');
            $p->transition('approve', from: 'review', to: 'done');
        });

        $stale = $this->workflows->start('stale-flow');
        $stale->fire('begin');

        // دستهٔ دیگر (Task) فرایند را تکمیل می‌کند
        $task = $this->workflows->tasksFor(5)[0];
        $fresh = $task->complete([], 'approve');
        self::assertTrue($fresh->isCompleted());

        // 🔒 دستهٔ کهنه پیش از fire با store همگام می‌شود → not_running (نه noToken)
        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessageId('workflow.not_running');
        $stale->fire('approve');
    }

    public function testRefreshPicksUpExternalChanges(): void
    {
        $this->defineOrderApproval();
        $handleA = $this->workflows->start('order-approval', null, null, ['amount' => 100]);
        $handleB = $this->workflows->instance($handleA->id);

        $handleB->fire('submit');
        self::assertSame('draft', $handleA->currentState());   // خواندن = حافظه (کهنه)

        $handleA->refresh();
        self::assertSame('pending_review', $handleA->currentState());
    }
}
