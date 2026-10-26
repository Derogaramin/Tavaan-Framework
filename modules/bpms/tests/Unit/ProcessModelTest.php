<?php

declare(strict_types=1);

namespace Modules\Bpms\Tests\Unit;

use Modules\Bpms\Domain\Enums\ElementType;
use Modules\Bpms\Domain\Enums\TaskPriority;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Services\ModelValidator;
use Modules\Bpms\Domain\ValueObjects\ProcessModel;
use Tavaan\Testing\TestCase;

/**
 * تست واحد ProcessModel + ModelValidator — ساختار تعریف فرایند
 * (DOC-098 §3 — AC-BPMS-11).
 */
final class ProcessModelTest extends TestCase
{
    /**
     * تعریف مرجع — دقیقاً نمونهٔ DOC-098 §3.
     *
     * @return array<string, mixed>
     */
    public static function leaveRequestJson(): array
    {
        return [
            'key' => 'leave-request',
            'name' => 'درخواست مرخصی',
            'version' => 1,
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
                 'assignee' => 'role:manager', 'form' => 'approval_form', 'due' => 'P2D', 'next' => 'decision'],
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
        ];
    }

    public function testDocumentedDefinitionParses(): void
    {
        $model = ProcessModel::fromArray(self::leaveRequestJson());

        self::assertSame('start', $model->startEvent);
        self::assertSame(ElementType::StartEvent, $model->startElement()->type);
        self::assertCount(9, $model->elements());

        $submit = $model->elementOrFail('submit');
        self::assertSame(ElementType::UserTask, $submit->type);
        self::assertSame('initiator', $submit->assignee);
        self::assertSame('leave_form', $submit->form);
        self::assertSame('check_days', $submit->next);

        $gateway = $model->elementOrFail('check_days');
        self::assertTrue($gateway->type->isGateway());
        self::assertCount(2, $gateway->conditions);
        self::assertSame('variables.days <= 2', $gateway->conditions[0]->expr);
        self::assertTrue($gateway->conditions[1]->default);

        $manager = $model->elementOrFail('manager_approval');
        self::assertSame('P2D', $manager->due);
        self::assertSame(TaskPriority::Normal, $manager->priority);

        // تایمرها
        $timers = $model->timersFor('manager_approval');
        self::assertCount(1, $timers);
        self::assertSame('escalate', $timers[0]->action);
        self::assertSame('P2D', $timers[0]->after);
        self::assertCount(0, $model->timersFor('submit'));

        // یال‌ها: notify دو ورودی دارد (auto_approve + شرط decision)
        self::assertSame(2, $model->incomingCount('notify'));
        self::assertSame(1, $model->incomingCount('decision'));
        self::assertSame(['auto_approve', 'manager_approval'], $model->elementOrFail('check_days')->outgoingTargets());
    }

    public function testDocumentedDefinitionIsValid(): void
    {
        $json = self::leaveRequestJson();
        $validator = new ModelValidator();
        $problems = $validator->validate($json, [
            'leave_form' => ['id' => 'leave_form', 'fields' => [['name' => 'days', 'type' => 'number', 'label' => 'روز']]],
            'approval_form' => ['id' => 'approval_form', 'fields' => [['name' => 'approved', 'type' => 'checkbox', 'label' => 'تأیید']]],
        ]);

        self::assertSame([], $problems);
    }

    public function testToArrayRoundTrip(): void
    {
        $model = ProcessModel::fromArray(self::leaveRequestJson());
        $again = ProcessModel::fromArray($model->toArray());

        self::assertSame($model->startEvent, $again->startEvent);
        self::assertCount(\count($model->elements()), $again->elements());
        self::assertCount(\count($model->timers), $again->timers);
        self::assertSame('P2D', $again->elementOrFail('manager_approval')->due);
    }

    public function testStartEventInferredWhenMissing(): void
    {
        $json = self::leaveRequestJson();
        unset($json['startEvent']);
        $model = ProcessModel::fromArray($json);
        self::assertSame('start', $model->startEvent);
    }

    public function testMissingStartEventRejected(): void
    {
        $json = self::leaveRequestJson();
        unset($json['startEvent']);
        $json['elements'][0]['type'] = 'userTask';   // هیچ startEvent نیست

        try {
            ProcessModel::fromArray($json);
            self::fail('باید خطا می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('invalid_definition', $e->reason());
        }
    }

    public function testDuplicateElementIdRejected(): void
    {
        try {
            ProcessModel::fromArray([
                'startEvent' => 'start',
                'elements' => [
                    ['id' => 'start', 'type' => 'startEvent', 'next' => 'start'],
                    ['id' => 'start', 'type' => 'endEvent'],
                ],
            ]);
            self::fail('باید خطا می‌داد');
        } catch (BpmsException $e) {
            self::assertSame('invalid_definition', $e->reason());
        }
    }

    // ─── ModelValidator: رد ساختارهای نامعتبر ──────────────────────────────

    public function testValidatorRejectsUnknownNext(): void
    {
        $problems = (new ModelValidator())->validate([
            'startEvent' => 'start',
            'elements' => [
                ['id' => 'start', 'type' => 'startEvent', 'next' => 'nowhere'],
                ['id' => 'end', 'type' => 'endEvent'],
            ],
        ]);

        self::assertNotEmpty($problems);
        self::assertTrue((bool) \array_filter($problems, static fn (string $p): bool => \str_contains($p, 'nowhere')));
    }

    public function testValidatorRejectsGatewayWithoutDefault(): void
    {
        $json = self::leaveRequestJson();
        // حذف default از check_days
        $json['elements'][2]['conditions'] = [
            ['expr' => 'variables.days <= 2', 'next' => 'auto_approve'],
        ];

        $problems = (new ModelValidator())->validate($json);
        self::assertNotEmpty($problems);
        self::assertTrue((bool) \array_filter($problems, static fn (string $p): bool => \str_contains($p, 'default')));
    }

    public function testValidatorRejectsServiceTaskWithoutAction(): void
    {
        $json = self::leaveRequestJson();
        unset($json['elements'][3]['action']);

        $problems = (new ModelValidator())->validate($json);
        self::assertTrue((bool) \array_filter($problems, static fn (string $p): bool => \str_contains($p, 'serviceTask')));
    }

    public function testValidatorRejectsMissingForm(): void
    {
        // submit به leave_form ارجاع دارد ولی form_schema خالی است
        $problems = (new ModelValidator())->validate(self::leaveRequestJson(), []);
        self::assertTrue((bool) \array_filter($problems, static fn (string $p): bool => \str_contains($p, 'leave_form')));
    }

    public function testValidatorRejectsBadTimer(): void
    {
        $json = self::leaveRequestJson();
        $json['timers'] = [['on' => 'ghost_state', 'after' => 'PXY', 'action' => '']];

        $problems = (new ModelValidator())->validate($json);
        self::assertTrue((bool) \array_filter($problems, static fn (string $p): bool => \str_contains($p, 'ghost_state')));
        self::assertTrue((bool) \array_filter($problems, static fn (string $p): bool => \str_contains($p, 'PXY')));
    }

    public function testValidatorRejectsUnreachableAndOrphanElements(): void
    {
        $problems = (new ModelValidator())->validate([
            'startEvent' => 'start',
            'elements' => [
                ['id' => 'start', 'type' => 'startEvent', 'next' => 'end'],
                ['id' => 'end', 'type' => 'endEvent'],
                ['id' => 'orphan', 'type' => 'serviceTask', 'action' => 'x'],
            ],
        ]);

        self::assertTrue((bool) \array_filter($problems, static fn (string $p): bool => \str_contains($p, 'orphan')));
    }

    public function testValidatorRejectsEndEventWithoutAnyEnd(): void
    {
        $problems = (new ModelValidator())->validate([
            'startEvent' => 'start',
            'elements' => [
                ['id' => 'start', 'type' => 'startEvent', 'next' => 'loop'],
                ['id' => 'loop', 'type' => 'serviceTask', 'action' => 'noop', 'next' => 'loop'],
            ],
        ]);

        self::assertTrue((bool) \array_filter($problems, static fn (string $p): bool => \str_contains($p, 'endEvent')));
    }

    public function testValidatorRejectsBadIds(): void
    {
        $problems = (new ModelValidator())->validate([
            'startEvent' => 'start',
            'elements' => [
                ['id' => 'start', 'type' => 'startEvent', 'next' => 'bad id!'],
                ['id' => 'bad id!', 'type' => 'endEvent'],
            ],
        ]);

        self::assertTrue((bool) \array_filter($problems, static fn (string $p): bool => \str_contains($p, 'شناسهٔ نامعتبر')));
    }

    public function testValidateDefinitionArrayChecksKeyAndName(): void
    {
        $validator = new ModelValidator();

        $problems = $validator->validateDefinitionArray([
            'key' => 'Bad Key!',
            'name' => '',
            'definition' => self::leaveRequestJson(),
        ]);
        self::assertNotEmpty($problems);

        $ok = $validator->validateDefinitionArray([
            'key' => 'leave-request',
            'name' => 'درخواست مرخصی',
            'definition' => self::leaveRequestJson(),
            'form_schema' => [
                'leave_form' => ['id' => 'leave_form', 'fields' => [['name' => 'days', 'type' => 'number', 'label' => 'روز']]],
                'approval_form' => ['id' => 'approval_form', 'fields' => [['name' => 'approved', 'type' => 'checkbox', 'label' => 'تأیید']]],
            ],
        ]);
        self::assertSame([], $ok);
    }

    public function testParallelGatewayValidation(): void
    {
        // fork معتبر + join معتبر
        $problems = (new ModelValidator())->validate([
            'startEvent' => 'start',
            'elements' => [
                ['id' => 'start', 'type' => 'startEvent', 'next' => 'split'],
                ['id' => 'split', 'type' => 'parallelGateway', 'fork' => ['a', 'b']],
                ['id' => 'a', 'type' => 'userTask', 'assignee' => 'role:x', 'next' => 'join'],
                ['id' => 'b', 'type' => 'userTask', 'assignee' => 'role:y', 'next' => 'join'],
                ['id' => 'join', 'type' => 'parallelGateway', 'next' => 'end'],
                ['id' => 'end', 'type' => 'endEvent'],
            ],
        ]);
        self::assertSame([], $problems);

        // gateway بی‌اثر (یک ورودی/یک خروجی)
        $degenerate = (new ModelValidator())->validate([
            'startEvent' => 'start',
            'elements' => [
                ['id' => 'start', 'type' => 'startEvent', 'next' => 'gw'],
                ['id' => 'gw', 'type' => 'parallelGateway', 'next' => 'end'],
                ['id' => 'end', 'type' => 'endEvent'],
            ],
        ]);
        self::assertNotEmpty($degenerate);
    }
}
