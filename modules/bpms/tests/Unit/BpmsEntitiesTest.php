<?php

declare(strict_types=1);

namespace Modules\Bpms\Tests\Unit;

use Modules\Bpms\Domain\Entities\ProcessInstance;
use Modules\Bpms\Domain\Entities\Task;
use Modules\Bpms\Domain\Enums\AssigneeType;
use Modules\Bpms\Domain\Enums\InstanceStatus;
use Modules\Bpms\Domain\Enums\TaskPriority;
use Modules\Bpms\Domain\Enums\TaskStatus;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Tavaan\Testing\TestCase;

/**
 * تست واحد موجودیت‌های Task و ProcessInstance — ماتریس وضعیت‌ها و
 * عملیات توکن (DOC-098 §2/§5).
 */
final class BpmsEntitiesTest extends TestCase
{
    private function makeTask(array $overrides = []): Task
    {
        return new Task(
            id: (string) ($overrides['id'] ?? '01TESTTASK000000000000000'),
            instanceId: (string) ($overrides['instanceId'] ?? '01INST0000000000000000000000'),
            stateId: (string) ($overrides['stateId'] ?? 'manager_approval'),
            title: (string) ($overrides['title'] ?? 'تأیید مدیر'),
            assigneeType: $overrides['assigneeType'] ?? AssigneeType::Role,
            assigneeId: \array_key_exists('assigneeId', $overrides) ? $overrides['assigneeId'] : 'manager',
            formData: (array) ($overrides['formData'] ?? []),
            status: $overrides['status'] ?? TaskStatus::Pending,
            priority: $overrides['priority'] ?? TaskPriority::Normal,
            formId: $overrides['formId'] ?? null,
            dueAt: $overrides['dueAt'] ?? null,
            claimedBy: $overrides['claimedBy'] ?? null,
            completedBy: $overrides['completedBy'] ?? null,
            createdAt: $overrides['createdAt'] ?? '2026-10-26 08:00:00.000',
            claimedAt: $overrides['claimedAt'] ?? null,
            completedAt: $overrides['completedAt'] ?? null,
        );
    }

    private function makeInstance(array $variables = [], array $overrides = []): ProcessInstance
    {
        return new ProcessInstance(
            id: (string) ($overrides['id'] ?? '01INST0000000000000000000000'),
            definitionId: (string) ($overrides['definitionId'] ?? '01DEF000000000000000000000'),
            definitionKey: (string) ($overrides['definitionKey'] ?? 'leave-request'),
            businessKey: $overrides['businessKey'] ?? null,
            initiatorId: $overrides['initiatorId'] ?? 1,
            currentState: (string) ($overrides['currentState'] ?? 'submit'),
            variables: $variables,
            startedAt: '2026-10-26 08:00:00.000',
            status: $overrides['status'] ?? InstanceStatus::Running,
        );
    }

    // ─── Task ──────────────────────────────────────────────────────────────

    public function testTaskClaimLifecycle(): void
    {
        $task = $this->makeTask();
        self::assertSame(TaskStatus::Pending, $task->status());

        $task->claim(5, '2026-10-26 09:00:00.000');
        self::assertSame(TaskStatus::Claimed, $task->status());
        self::assertSame(5, $task->claimedBy());
        self::assertSame('2026-10-26 09:00:00.000', $task->claimedAt());
        self::assertSame(AssigneeType::User, $task->assigneeType());
        self::assertSame('5', $task->assigneeId());

        // claim دوباره توسط دیگری → رد
        try {
            $task->claim(6, '2026-10-26 10:00:00.000');
            self::fail('باید already_claimed می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('already_claimed', $e->reason());
        }

        // claim مجدد توسط خود کاربر → بی‌اثر (بدون خطا)
        $task->claim(5, '2026-10-26 10:00:00.000');
        self::assertSame(5, $task->claimedBy());
    }

    public function testTaskComplete(): void
    {
        $task = $this->makeTask();
        $task->complete(['approved' => true], 7, '2026-10-26 11:00:00.000', 'موافقت شد');

        self::assertSame(TaskStatus::Completed, $task->status());
        self::assertSame(['approved' => true], $task->formData());
        self::assertSame(7, $task->completedBy());
        self::assertSame('موافقت شد', $task->comment());

        // تکمیل دوباره → رد
        try {
            $task->complete([], 7, '2026-10-26 12:00:00.000');
            self::fail('باید task_not_open می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('task_not_open', $e->reason());
        }
    }

    public function testTaskDelegateResetsClaim(): void
    {
        $task = $this->makeTask();
        $task->claim(5, '2026-10-26 09:00:00.000');
        $task->delegate(AssigneeType::User, '9');

        self::assertSame(TaskStatus::Pending, $task->status());
        self::assertNull($task->claimedBy());
        self::assertNull($task->claimedAt());
        self::assertSame(AssigneeType::User, $task->assigneeType());
        self::assertSame('9', $task->assigneeId());
    }

    public function testTaskEscalateReassignsAndStamps(): void
    {
        $task = $this->makeTask();
        $task->claim(5, '2026-10-26 09:00:00.000');
        $task->escalate(AssigneeType::Role, 'process-admin', '2026-10-28 09:00:00.000');

        self::assertTrue($task->isEscalated());
        self::assertSame('role:process-admin', $task->escalatedTo());
        self::assertSame(AssigneeType::Role, $task->assigneeType());
        self::assertSame(TaskStatus::Pending, $task->status());
        self::assertNull($task->claimedBy());
    }

    public function testTaskCancelOnTerminalIsNoop(): void
    {
        $task = $this->makeTask();
        $task->cancel();
        self::assertSame(TaskStatus::Canceled, $task->status());

        $completed = $this->makeTask(['status' => TaskStatus::Completed]);
        $completed->cancel();
        self::assertSame(TaskStatus::Completed, $completed->status());   // تغییر نکرد
    }

    public function testTaskOverdue(): void
    {
        $now = new \DateTimeImmutable('2026-10-26 12:00:00');

        $overdue = $this->makeTask(['dueAt' => '2026-10-26 10:00:00.000']);
        self::assertTrue($overdue->isOverdue($now));

        $future = $this->makeTask(['dueAt' => '2026-10-27 10:00:00.000']);
        self::assertFalse($future->isOverdue($now));

        $noDue = $this->makeTask();
        self::assertFalse($noDue->isOverdue($now));

        $done = $this->makeTask(['dueAt' => '2026-10-26 10:00:00.000', 'status' => TaskStatus::Completed]);
        self::assertFalse($done->isOverdue($now));   // بسته → overdue نیست
    }

    public function testAssigneeParsing(): void
    {
        self::assertSame([AssigneeType::Initiator, null], AssigneeType::parse('initiator'));
        self::assertSame([AssigneeType::Role, 'manager'], AssigneeType::parse('role:manager'));
        self::assertSame([AssigneeType::User, '12'], AssigneeType::parse('user:12'));
        self::assertSame([AssigneeType::Group, 'g1'], AssigneeType::parse('group:g1'));
        self::assertSame([AssigneeType::User, '7'], AssigneeType::parse('7'));      // عدد → user
        self::assertSame([AssigneeType::Role, 'finance'], AssigneeType::parse('finance')); // نام → role
        self::assertSame([AssigneeType::Anyone, null], AssigneeType::parse(null));
        self::assertSame([AssigneeType::Anyone, null], AssigneeType::parse(''));
    }

    // ─── ProcessInstance ───────────────────────────────────────────────────

    public function testInstanceTokensRoundTrip(): void
    {
        $instance = $this->makeInstance(['days' => 5, '_tokens' => ['a', 'b'], '_ended' => 1, '_joins' => ['j' => 1]]);

        self::assertSame(['a', 'b'], $instance->tokens());
        self::assertSame(['days' => 5], $instance->variables());   // رزروها نشت نمی‌کنند
        self::assertSame(['j' => 1], $instance->joins());
        self::assertSame(1, $instance->endedCount());

        $serialized = $instance->serializeVariables();
        self::assertSame(['a', 'b'], $serialized['_tokens']);
        self::assertSame(1, $serialized['_ended']);
        self::assertSame(5, $serialized['days']);
    }

    public function testMoveToAndTokenOps(): void
    {
        $instance = $this->makeInstance(['_tokens' => ['a']]);

        $instance->moveTo('a', 'b');
        self::assertSame(['b'], $instance->tokens());
        self::assertSame('b', $instance->currentState());

        // fork
        $instance->addToken('c');
        self::assertSame(['b', 'c'], $instance->tokens());

        // پایان یکی از توکن‌ها
        self::assertTrue($instance->removeToken('b'));
        self::assertSame(['c'], $instance->tokens());
        self::assertSame(1, $instance->endedCount());
        self::assertFalse($instance->allTokensEnded());

        // پایان آخرین توکن
        $instance->removeToken('c');
        self::assertTrue($instance->allTokensEnded());

        // moveTo بدون توکن → خطا
        try {
            $instance->moveTo('ghost', 'x');
            self::fail('باید خطا می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('invalid_definition', $e->reason());
        }
    }

    public function testJoinArrivalCounting(): void
    {
        $instance = $this->makeInstance(['_tokens' => ['j', 'j']]);

        // توکن اول → نارسیده
        self::assertFalse($instance->arriveAtJoin('j', 2));
        // توکن دوم → کامل
        self::assertTrue($instance->arriveAtJoin('j', 2));
        self::assertSame([], $instance->joins());

        // مصرف توکن‌های پارک‌شده
        self::assertTrue($instance->consumeToken('j'));
        self::assertTrue($instance->consumeToken('j'));
        self::assertFalse($instance->consumeToken('j'));
    }

    public function testMergeVariablesRejectsReservedKeys(): void
    {
        $instance = $this->makeInstance(['_tokens' => ['a']]);

        $instance->mergeVariables([
            'days' => 3,
            '_tokens' => ['evil'],
            '_ended' => 99,
            '_private' => 'x',
            5 => 'numeric-key-ignored',
        ]);

        self::assertSame(['a'], $instance->tokens());        // دست‌نخورده
        self::assertSame(0, $instance->endedCount());        // دست‌نخورده
        self::assertSame(3, $instance->getVariable('days'));
        self::assertNull($instance->getVariable('_private'));
    }

    public function testInstanceStatusTransitions(): void
    {
        $instance = $this->makeInstance(['_tokens' => ['a']]);

        $instance->suspend();
        self::assertSame(InstanceStatus::Suspended, $instance->status());
        self::assertFalse($instance->isRunning());

        // حرکت در suspend → رد
        try {
            $instance->moveTo('a', 'b');
            self::fail('باید not_running می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('not_running', $e->reason());
        }

        $instance->resume();
        self::assertTrue($instance->isRunning());

        $instance->terminate('2026-10-26 12:00:00.000');
        self::assertSame(InstanceStatus::Terminated, $instance->status());
        self::assertTrue($instance->isTerminal());
        self::assertSame([], $instance->tokens());

        // terminate دوباره → رد
        try {
            $instance->terminate('2026-10-26 13:00:00.000');
            self::fail('باید not_running می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('not_running', $e->reason());
        }
    }

    public function testCompleteSetsTimestampAndClearsTokens(): void
    {
        $instance = $this->makeInstance(['_tokens' => ['end'], '_ended' => 1]);
        $instance->complete('2026-10-26 14:00:00.000');

        self::assertSame(InstanceStatus::Completed, $instance->status());
        self::assertSame('2026-10-26 14:00:00.000', $instance->completedAt());
        self::assertSame([], $instance->tokens());
    }

    public function testFromRowHydration(): void
    {
        $instance = ProcessInstance::fromRow([
            'id' => '01X',
            'definition_id' => '01D',
            'definition_key' => 'k',
            'business_key' => 'BK-1',
            'initiator_id' => 3,
            'current_state' => 'b',
            'status' => 'suspended',
            'variables' => ['x' => 1, '_tokens' => ['b'], '_ended' => 0],
            'started_at' => '2026-10-26 08:00:00.000',
            'completed_at' => null,
            'workflow_instance_id' => '01W',
        ]);

        self::assertSame('BK-1', $instance->businessKey());
        self::assertSame(3, $instance->initiatorId());
        self::assertSame(InstanceStatus::Suspended, $instance->status());
        self::assertSame(['b'], $instance->tokens());
        self::assertSame(1, $instance->getVariable('x'));
        self::assertSame('01W', $instance->workflowInstanceId());
    }
}
