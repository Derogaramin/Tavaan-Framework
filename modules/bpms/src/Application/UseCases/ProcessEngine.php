<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ProcessEngine — موتور اجرای فرایند (DOC-098 §5).
 */

namespace Modules\Bpms\Application\UseCases;

use Modules\Bpms\Domain\Entities\HistoryEntry;
use Modules\Bpms\Domain\Entities\ProcessDefinition;
use Modules\Bpms\Domain\Entities\ProcessInstance;
use Modules\Bpms\Domain\Entities\Task;
use Modules\Bpms\Domain\Enums\AssigneeType;
use Modules\Bpms\Domain\Enums\ElementType;
use Modules\Bpms\Domain\Enums\InstanceStatus;
use Modules\Bpms\Domain\Enums\TaskStatus;
use Modules\Bpms\Domain\Events\ProcessWasCompleted;
use Modules\Bpms\Domain\Events\ProcessWasStarted;
use Modules\Bpms\Domain\Events\ProcessWasTerminated;
use Modules\Bpms\Domain\Events\TaskWasCompleted;
use Modules\Bpms\Domain\Events\TaskWasCreated;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Modules\Bpms\Domain\Repositories\HistoryRepositoryInterface;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Modules\Bpms\Domain\Repositories\RoleReaderInterface;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Modules\Bpms\Domain\Services\DurationParser;
use Modules\Bpms\Domain\Services\ExpressionEvaluator;
use Modules\Bpms\Domain\ValueObjects\Element;
use Modules\Bpms\Domain\ValueObjects\ProcessModel;
use Modules\Bpms\Infrastructure\Workflow\WorkflowBridge;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * ProcessEngine — start/completeTask/claim/delegate/terminate/suspend/resume
 * و `advance` عنصر-محور (§5): userTask → ساخت کار و توقف؛ serviceTask →
 * اکشن ثبت‌شده؛ exclusiveGateway → ارزیابی شرط با ExpressionEvaluator امن؛
 * parallelGateway → fork/join با توکن؛ endEvent → تکمیل نمونه.
 *
 * 🔒 مرجع صحت: جداول bpms_* — mirror موتور workflow فقط تاریخچه/
 * visualization (WorkflowBridge — best-effort).
 * 🔒 تراکنش: هر عملیات کسب‌وکار در DB::transaction؛ رویدادها پس از commit.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class ProcessEngine
{
    /** @param array<string, mixed> $config کلید config('bpms') */
    public function __construct(
        private readonly DefinitionRepositoryInterface $definitions,
        private readonly InstanceRepositoryInterface $instances,
        private readonly TaskRepositoryInterface $tasks,
        private readonly HistoryRepositoryInterface $history,
        private readonly RoleReaderInterface $roles,
        private readonly ActionRegistry $actions,
        private readonly ExpressionEvaluator $evaluator,
        private readonly WorkflowBridge $bridge,
        private readonly ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    // ─── §5: start ─────────────────────────────────────────────────────────

    /**
     * آغاز نمونهٔ فرایند و پیش‌برد تا نخستین حالت انتظار.
     *
     * @param array<string, mixed> $variables
     *
     * @throws BpmsException no_active_definition / too_many_instances
     */
    public function start(string $definitionKey, array $variables = [], ?int $initiatorId = null, ?string $businessKey = null): ProcessInstance
    {
        $definition = $this->definitions->findActiveByKey($definitionKey)
            ?? throw BpmsException::noActiveDefinition($definitionKey);

        // 🔒 محافظ DoS: سقف نمونه‌های فعال per تعریف (DOC-071 §8)
        $limit = (int) ($this->config['max_active_instances'] ?? 0);
        if ($limit > 0 && $this->instances->countActiveForDefinition($definition->id()) >= $limit) {
            throw BpmsException::tooManyInstances($definitionKey, $limit);
        }

        $startId = $definition->model()->startEvent;
        $now = Clock::now();
        $nowIso = $now->format('Y-m-d H:i:s.v');

        $instance = new ProcessInstance(
            id: Ulid::generate($now)->toString(),
            definitionId: $definition->id(),
            definitionKey: $definition->key(),
            businessKey: $businessKey,
            initiatorId: $initiatorId,
            currentState: $startId,
            variables: [...$variables, '_tokens' => [$startId], '_ended' => 0],
            startedAt: $nowIso,
            status: InstanceStatus::Running,
        );

        /** @var list<object> $events */
        $events = [];
        DB::transaction(function () use ($instance, $startId, $initiatorId, $nowIso, $definition, &$events): void {
            $this->instances->create($instance);
            $this->recordHistory($instance->id(), null, $startId, 'start', $initiatorId, 'system', $nowIso);

            // mirror (best-effort — پیش از advance تا حرکت‌ها fire شوند)
            $mirrorId = $this->bridge->startInstance($instance, $instance->variables());
            if ($mirrorId !== null) {
                $instance->setWorkflowInstanceId($mirrorId);
            }

            $this->advance($instance, $definition, $initiatorId, $events);
            $this->instances->update($instance);
        });

        $this->dispatchAll([
            new ProcessWasStarted(
                $instance->id(),
                $definition->key(),
                $initiatorId,
                $instance->businessKey(),
                $instance->variables(),
            ),
            ...$events,
        ]);

        return $instance;
    }

    // ─── §5: completeTask ──────────────────────────────────────────────────

    /**
     * تکمیل کار انسانی: authorize → validate فرم → ادغام متغیرها → advance.
     *
     * @param array<string, mixed> $formData
     * @param list<string>         $actorRoles
     *
     * @throws BpmsException       task_not_found / not_running / not_assignee
     * @throws ValidationException دادهٔ فرم نامعتبر
     */
    public function completeTask(string $taskId, array $formData, int $actorId, array $actorRoles = [], ?string $comment = null, bool $canOverride = false): void
    {
        $task = $this->tasks->findById($taskId) ?? throw BpmsException::taskNotFound($taskId);
        $instance = $this->instances->findById($task->instanceId()) ?? throw BpmsException::instanceNotFound($task->instanceId());
        if (!$instance->isRunning()) {
            throw BpmsException::notRunning($instance->id(), $instance->status()->value);
        }
        $definition = $this->definitions->findById($instance->definitionId())
            ?? throw BpmsException::definitionNotFound($instance->definitionId());

        $this->authorizeTask($task, $instance, $actorId, $actorRoles, $canOverride);

        $element = $definition->model()->elementOrFail($task->stateId());
        $validated = $this->validateFormData($definition, $element, $formData);

        $nowIso = Clock::now()->format('Y-m-d H:i:s.v');
        $durationMs = $this->dwellMs($task->createdAt(), $nowIso);
        $onTime = $task->dueAt() === null || $nowIso <= $task->dueAt();

        /** @var list<object> $events */
        $events = [];
        DB::transaction(function () use ($task, $instance, $definition, $element, $validated, $actorId, $comment, $nowIso, $durationMs, $onTime, &$events): void {
            $task->complete($validated, $actorId, $nowIso, $comment);
            $this->tasks->update($task);

            $instance->mergeVariables($validated);

            $next = $element->next ?? throw BpmsException::unsupportedElement($element->id, $element->type->value);
            $this->recordHistory($instance->id(), $task->stateId(), $next, 'task', $actorId, 'user', $nowIso, $durationMs);
            $this->moveToken($instance, $task->stateId(), $next);

            $events[] = new TaskWasCompleted(
                $task->id(),
                $instance->id(),
                $task->stateId(),
                $actorId,
                $durationMs,
                $onTime,
            );

            // فقط توکنِ جابجاشده صف می‌شود — توکن‌های پارک‌شدهٔ موازی دست‌نخورده می‌مانند
            $this->advance($instance, $definition, $actorId, $events, [$next]);
            $this->instances->update($instance);
        });

        $this->dispatchAll($events);
    }

    // ─── claim / delegate ──────────────────────────────────────────────────

    /**
     * تصاحب کار (اتمیک — رقابت: فقط یک برنده).
     *
     * @param list<string> $actorRoles
     *
     * @throws BpmsException already_claimed / not_assignee
     */
    public function claim(string $taskId, int $actorId, array $actorRoles = []): void
    {
        $task = $this->tasks->findById($taskId) ?? throw BpmsException::taskNotFound($taskId);
        $instance = $this->instances->findById($task->instanceId());
        if ($instance !== null && !$instance->isRunning()) {
            throw BpmsException::notRunning($instance->id(), $instance->status()->value);
        }
        $this->assertCanWork($task, $instance, $actorId, $actorRoles);

        $nowIso = Clock::now()->format('Y-m-d H:i:s.v');
        if (!$this->tasks->claimAtomic($taskId, $actorId, $nowIso)) {
            throw BpmsException::alreadyClaimed($taskId);
        }
    }

    /**
     * واگذاری/انتصاب کار به دیگری (خودِ assignee یا مدیر با canOverride).
     *
     * @param list<string> $actorRoles
     */
    public function delegate(string $taskId, int $actorId, array $actorRoles, string $targetType, string $targetId, bool $canOverride = false): void
    {
        $type = AssigneeType::fromString($targetType);
        if (!\in_array($type, [AssigneeType::User, AssigneeType::Role, AssigneeType::Group], true) || $targetId === '') {
            throw BpmsException::notAssignee($taskId);
        }

        $task = $this->tasks->findById($taskId) ?? throw BpmsException::taskNotFound($taskId);
        $instance = $this->instances->findById($task->instanceId());
        if ($instance !== null && !$instance->isRunning()) {
            throw BpmsException::notRunning($instance->id(), $instance->status()->value);
        }
        if (!$canOverride) {
            $this->assertCanWork($task, $instance, $actorId, $actorRoles);
        }

        $task->delegate($type, $targetId);
        $this->tasks->update($task);

        // assignee جدید از راه همان رویداد task.created مطلع می‌شود (README)
        $this->dispatchAll([new TaskWasCreated(
            $task->id(),
            $task->instanceId(),
            $task->stateId(),
            $task->title(),
            $type->value,
            $targetId,
            $task->dueAt(),
            $instance?->initiatorId(),
        )]);
    }

    // ─── مدیریت نمونه (§7: process-admin) ─────────────────────────────────

    /** پایان اجباری نمونه: لغو کارهای باز + mirror cancel + رویداد. */
    public function terminate(string $instanceId, int $actorId, ?string $reason = null): void
    {
        $instance = $this->instances->findById($instanceId) ?? throw BpmsException::instanceNotFound($instanceId);
        if ($instance->isTerminal()) {
            throw BpmsException::notRunning($instanceId, $instance->status()->value);
        }

        $nowIso = Clock::now()->format('Y-m-d H:i:s.v');
        DB::transaction(function () use ($instance, $actorId, $nowIso): void {
            $this->tasks->cancelOpenForInstance($instance->id());
            $this->recordHistory($instance->id(), $instance->currentState(), '__terminated', 'terminate', $actorId, 'user', $nowIso);
            $instance->terminate($nowIso);
            $this->instances->update($instance);
            $this->bridge->cancelInstance($instance->workflowInstanceId());
        });

        $this->dispatchAll([new ProcessWasTerminated(
            $instance->id(),
            $instance->definitionKey(),
            $instance->initiatorId(),
            $actorId,
            $reason,
        )]);
    }

    public function suspend(string $instanceId, int $actorId): void
    {
        $instance = $this->instances->findById($instanceId) ?? throw BpmsException::instanceNotFound($instanceId);
        $instance->suspend();
        $nowIso = Clock::now()->format('Y-m-d H:i:s.v');
        $this->recordHistory($instance->id(), $instance->currentState(), $instance->currentState(), 'suspend', $actorId, 'user', $nowIso);
        $this->instances->update($instance);
    }

    public function resume(string $instanceId, int $actorId): void
    {
        $instance = $this->instances->findById($instanceId) ?? throw BpmsException::instanceNotFound($instanceId);
        $instance->resume();
        $nowIso = Clock::now()->format('Y-m-d H:i:s.v');
        $this->recordHistory($instance->id(), $instance->currentState(), $instance->currentState(), 'resume', $actorId, 'user', $nowIso);
        $this->instances->update($instance);
    }

    // ─── §5: advance ───────────────────────────────────────────────────────

    /**
     * پیش‌برد نمونه تا توقف همهٔ توکن‌ها در userTask یا رسیدن به پایان.
     *
     * الگوی work-queue: هر توکنِ جابجاشده دقیقاً یک‌بار پردازش و مقصدش
     * صف می‌شود؛ توکن‌های پارک‌شده (join ناقص / userTask) هرگز
     * بازپردازش نمی‌شوند (جلوگیری از join زودهنگام).
     *
     * @param list<object> $events رویدادهای جمع‌شده (پس از commit dispatch می‌شوند)
     * @param list<string> $queue  توکن‌های تازهٔ ورودی (پیش‌فرض: همهٔ توکن‌های فعال)
     *
     * @throws BpmsException advance_limit / gateway_stuck / unknown_action
     */
    private function advance(ProcessInstance $instance, ProcessDefinition $definition, ?int $actorId, array &$events, ?array $queue = null): void
    {
        $model = $definition->model();
        $maxSteps = (int) ($this->config['advance_limit'] ?? 1000);
        $steps = 0;

        $queue = $queue ?? $instance->tokens();

        while ($queue !== []) {
            if (++$steps > $maxSteps) {
                throw BpmsException::advanceLimit($instance->id(), $maxSteps);
            }
            if (!$instance->isRunning()) {
                return;
            }

            $stateId = (string) \array_shift($queue);
            $element = $model->elementOrFail($stateId);

            // userTask → کار انسانی ساخته/اطمینان می‌شود و توکن منتظر می‌ماند
            if ($element->type === ElementType::UserTask) {
                $this->ensureTask($instance, $element, $events);
                continue;
            }

            // endEvent → توکن پایان می‌یابد
            if ($element->type === ElementType::EndEvent) {
                $instance->removeToken($stateId);
                continue;
            }

            $next = match ($element->type) {
                ElementType::StartEvent => $this->stepSimple($instance, $element, 'start', $actorId),
                ElementType::ServiceTask => $this->stepService($instance, $element, $actorId),
                ElementType::ExclusiveGateway => $this->stepExclusive($instance, $element, $actorId),
                ElementType::ParallelGateway => $this->stepParallel($instance, $element, $model, $actorId),
                default => throw BpmsException::unsupportedElement($element->id, $element->type->value),
            };

            foreach ($next as $target) {
                $queue[] = $target;
            }
        }

        if ($instance->allTokensEnded()) {
            $completedAt = Clock::now()->format('Y-m-d H:i:s.v');
            $instance->complete($completedAt);
            $events[] = new ProcessWasCompleted(
                $instance->id(),
                $instance->definitionKey(),
                $instance->initiatorId(),
                $instance->businessKey(),
                $this->dwellMs($instance->startedAt(), $completedAt),
            );
        }
    }

    /** startEvent — عبور مستقیم. @return list<string> مقصد */
    private function stepSimple(ProcessInstance $instance, Element $element, string $transition, ?int $actorId): array
    {
        $next = $element->next ?? throw BpmsException::unsupportedElement($element->id, $element->type->value);
        $this->recordMove($instance, $element->id, $next, $transition, $actorId);

        return [$next];
    }

    /** serviceTask — اکشن ثبت‌شده سپس عبور. @return list<string> مقصد */
    private function stepService(ProcessInstance $instance, Element $element, ?int $actorId): array
    {
        $action = (string) ($element->action ?? '');
        if ($action === '') {
            throw BpmsException::unsupportedElement($element->id, 'serviceTask بدون action');
        }

        // §6: اکشن از پیش ثبت‌شده (نه از ورودی کاربر)
        $context = new ProcessContext($instance, $this->container, actorId: $actorId);
        $this->actions->execute($action, $context);

        $next = $element->next ?? throw BpmsException::unsupportedElement($element->id, $element->type->value);
        $this->recordMove($instance, $element->id, $next, 'service:' . $action, $actorId);

        return [$next];
    }

    /** exclusiveGateway — اولین شرط مطابق / default (§5). @return list<string> مقصد */
    private function stepExclusive(ProcessInstance $instance, Element $gateway, ?int $actorId): array
    {
        $chosen = $this->evaluateGateway($instance, $gateway);
        $this->recordMove($instance, $gateway->id, $chosen, 'gateway:' . $gateway->id, $actorId);

        return [$chosen];
    }

    /**
     * parallelGateway — join (چند ورودی: انتظار همهٔ توکن‌ها) یا fork
     * (یک توکن → یک توکن per خروجی) — E2E-05.
     *
     * @return list<string> مقصدهایی که باید پردازش شوند
     */
    private function stepParallel(ProcessInstance $instance, Element $gateway, ProcessModel $model, ?int $actorId): array
    {
        $stateId = $gateway->id;
        $outgoing = $gateway->outgoingTargets();
        if ($outgoing === []) {
            throw BpmsException::unsupportedElement($stateId, 'parallelGateway بدون خروجی');
        }

        $incoming = $model->incomingCount($stateId);

        // join: آخرین توکن که رسید همهٔ توکن‌های پارک‌شده را مصرف می‌کند
        if ($incoming > 1 && \count($outgoing) === 1) {
            if (!$instance->arriveAtJoin($stateId, $incoming)) {
                return [];   // توکن جاری پارک می‌ماند (در tokens باقی است)
            }
            for ($i = 0; $i < $incoming; $i++) {
                if (!$instance->consumeToken($stateId)) {
                    break;
                }
            }
            $target = $outgoing[0];
            $this->recordMove($instance, $stateId, $target, 'join:' . $stateId, $actorId, addToken: true);

            return [$target];
        }

        // fork: توکن جاری مصرف + یک توکن per خروجی
        $instance->consumeToken($stateId);
        foreach ($outgoing as $target) {
            $this->recordMove($instance, $stateId, $target, 'fork:' . $stateId, $actorId, addToken: true);
        }

        return $outgoing;
    }

    /**
     * userTask: ساخت کار (یک‌بار per ورود توکن — idempotent) → رویداد created.
     *
     * @param list<object> $events
     */
    private function ensureTask(ProcessInstance $instance, Element $element, array &$events): void
    {
        // کار باز برای این state موجود است (ورود مجدد/بازیابی) → ساخت نه
        foreach ($this->tasks->forInstance($instance->id()) as $existing) {
            if ($existing->stateId() === $element->id && $existing->status()->isOpen()) {
                return;
            }
        }

        $now = Clock::now();
        [$assigneeType, $assigneeId] = AssigneeType::parse($element->assignee);
        // initiator → شناسهٔ آغازگر نمونه در assignee_id حل می‌شود (inbox/اعلان)
        if ($assigneeType === AssigneeType::Initiator && $instance->initiatorId() !== null) {
            $assigneeId = (string) $instance->initiatorId();
        }
        $dueAt = $element->due !== null && $element->due !== ''
            ? DurationParser::add($now, $element->due)->format('Y-m-d H:i:s.v')
            : null;

        $task = new Task(
            id: Ulid::generate($now)->toString(),
            instanceId: $instance->id(),
            stateId: $element->id,
            title: $element->name ?? $element->id,
            assigneeType: $assigneeType,
            assigneeId: $assigneeId,
            formData: [],
            status: TaskStatus::Pending,
            priority: $element->priority,
            formId: $element->form,
            dueAt: $dueAt,
            claimedBy: null,
            completedBy: null,
            createdAt: $now->format('Y-m-d H:i:s.v'),
            claimedAt: null,
            completedAt: null,
        );
        $this->tasks->create($task);

        $events[] = new TaskWasCreated(
            $task->id(),
            $instance->id(),
            $element->id,
            $task->title(),
            $assigneeType->value,
            $task->assigneeId(),
            $dueAt,
            $instance->initiatorId(),
        );
    }

    /** اجرای اکشن ثبت‌شدهٔ serviceTask (§6). */
    private function runService(ProcessInstance $instance, Element $element, ?int $actorId): void
    {
        $action = (string) ($element->action ?? '');
        if ($action === '') {
            throw BpmsException::unsupportedElement($element->id, 'serviceTask بدون action');
        }

        $context = new ProcessContext($instance, $this->container, actorId: $actorId);
        $this->actions->execute($action, $context);
    }

    /** دروازهٔ انحصاری — اولین شرط مطابق؛ default در پایان (§5 evaluateGateway). */
    private function evaluateGateway(ProcessInstance $instance, Element $gateway): string
    {
        $context = ['variables' => $instance->variables()];
        $default = null;

        foreach ($gateway->conditions as $condition) {
            if ($condition->default) {
                $default ??= $condition->next;
                continue;
            }
            if ($condition->expr !== null && $condition->next !== '' && $this->evaluator->evaluate($condition->expr, $context)) {
                return $condition->next;
            }
        }

        if ($default !== null && $default !== '') {
            return $default;
        }

        throw BpmsException::gatewayStuck($gateway->id);
    }

    // ─── تاریخچه/mirror ────────────────────────────────────────────────────

    /** ثبت حرکت توکن: history + moveTo/addToken + fire در mirror. */
    private function recordMove(ProcessInstance $instance, string $from, string $to, string $transition, ?int $actorId, bool $addToken = false): void
    {
        $this->recordHistory($instance->id(), $from, $to, $transition, $actorId, 'system', Clock::now()->format('Y-m-d H:i:s.v'));
        if ($addToken) {
            $instance->addToken($to);
        } else {
            $instance->moveTo($from, $to);
        }
        $this->bridge->fireMove($instance->workflowInstanceId(), $from, $to);
    }

    private function moveToken(ProcessInstance $instance, string $from, string $to): void
    {
        $instance->moveTo($from, $to);
        $this->bridge->fireMove($instance->workflowInstanceId(), $from, $to);
    }

    private function recordHistory(
        string $instanceId,
        ?string $from,
        string $to,
        string $transition,
        ?int $actorId,
        string $trigger,
        string $occurredAt,
        ?int $durationMs = null,
    ): void {
        $this->history->record(new HistoryEntry(
            id: 0,
            instanceId: $instanceId,
            fromState: $from,
            toState: $to,
            transition: $transition,
            actorId: $actorId,
            trigger: $trigger,
            occurredAt: $occurredAt,
            durationMs: $durationMs,
        ));
    }

    /** @param list<object> $events */
    private function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch((string) $event->eventName(), (array) $event->toPayload());
            }
        }
    }

    // ─── فرم (§4) ──────────────────────────────────────────────────────────

    /**
     * نرمال‌سازی + اعتبارسنجی دادهٔ فرم task با schema تعریف (E2E-07).
     *
     * @param array<string, mixed> $formData
     *
     * @return array<string, mixed> دادهٔ نرمال‌شده (فقط فیلدهای schema)
     *
     * @throws ValidationException
     */
    private function validateFormData(ProcessDefinition $definition, Element $element, array $formData): array
    {
        $formId = $element->form;
        if ($formId === null || $formId === '') {
            return [];   // بدون فرم — دادهٔ ورودی هرگز در متغیرها ادغام نمی‌شود 🔒
        }

        $schema = $definition->formSchema($formId);
        if ($schema === null) {
            return [];
        }

        $normalized = $schema->normalize($formData);
        $rules = $schema->toValidationRules();
        if ($rules === []) {
            return $normalized;
        }

        $attributes = [];
        foreach ($schema->fields as $field) {
            $attributes[$field->name] = $field->label;
        }
        $validated = Validator::validate($normalized, $rules, [], $attributes);

        return \is_array($validated) ? $validated : $normalized;
    }

    // ─── مجوزها (§7/§8 امنیت) ──────────────────────────────────────────────

    /**
     * @param list<string> $actorRoles
     *
     * @throws BpmsException not_assignee
     */
    private function authorizeTask(Task $task, ProcessInstance $instance, int $actorId, array $actorRoles, bool $canOverride): void
    {
        if ($canOverride || $this->canWork($task, $instance, $actorId, $actorRoles)) {
            return;
        }

        throw BpmsException::notAssignee($task->id());
    }

    /** @param list<string> $actorRoles */
    private function assertCanWork(Task $task, ?ProcessInstance $instance, int $actorId, array $actorRoles): void
    {
        if (!$this->canWork($task, $instance, $actorId, $actorRoles)) {
            throw BpmsException::notAssignee($task->id());
        }
    }

    /**
     * آیا کاربر مجاز به کار روی task است؟
     * user → خود؛ initiator → آغازگر نمونه؛ role/group → عضویت نقش؛
     * anyone → هر دارندهٔ مجوز claim (کنترلر مجوز را بررسی می‌کند).
     *
     * @param list<string> $actorRoles
     */
    private function canWork(Task $task, ?ProcessInstance $instance, int $actorId, array $actorRoles): bool
    {
        return match ($task->assigneeType()) {
            AssigneeType::User => $task->assigneeId() === (string) $actorId,
            AssigneeType::Initiator => $instance !== null && $instance->initiatorId() === $actorId,
            AssigneeType::Role, AssigneeType::Group => $task->assigneeId() !== null
                && \in_array($task->assigneeId(), $actorRoles, true),
            AssigneeType::Anyone => true,
        };
    }

    /** نقش‌های کاربر (RBAC — برای کنترلرها/سرویس‌ها). @return list<string> */
    public function rolesOf(int $userId): array
    {
        return $this->roles->rolesOf($userId);
    }

    /** کارهای باز یک نمونه (تسهیل برای seed/CLI/تست). @return list<Task> */
    public function openTasksOf(string $instanceId): array
    {
        return \array_values(\array_filter(
            $this->tasks->forInstance($instanceId),
            static fn (Task $task): bool => $task->status()->isOpen(),
        ));
    }

    // ─── داخلی ─────────────────────────────────────────────────────────────

    private function dwellMs(?string $fromIso, ?string $toIso): ?int
    {
        if ($fromIso === null || $toIso === null) {
            return null;
        }
        try {
            $from = new \DateTimeImmutable($fromIso);
            $to = new \DateTimeImmutable($toIso);
        } catch (\Exception) {
            return null;
        }
        $ms = ($to->getTimestamp() - $from->getTimestamp()) * 1000
            + ((int) $to->format('v') - (int) $from->format('v'));

        return $ms >= 0 ? $ms : null;
    }
}
