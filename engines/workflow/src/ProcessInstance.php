<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Exceptions\Authorization\AuthorizationException;
use Tavaan\Engine\Workflow\Events\ProcessTransitioned;
use Tavaan\Engine\Workflow\Events\TaskCreated;
use Tavaan\Engine\Workflow\Events\WorkflowCanceled;
use Tavaan\Engine\Workflow\Events\WorkflowCompleted;
use Tavaan\Engine\Workflow\Exceptions\IllegalTransitionException;
use Tavaan\Engine\Workflow\Exceptions\WorkflowException;
use Tavaan\Engine\Workflow\Store\StoreInterface;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Workflow
 * ProcessInstance — یک اجرای فرایند (DOC-071 §4): fire با Guard/Action،
 * Token برای Fork/Join، Human Task، Timer، Sub-Process و History.
 *
 * 🔒 تغییر state فقط از طریق `fire()`/`fireTimer()` (DOC-071 §8).
 * 🔒 توکن‌ها/تایمرها/پیوند والد در کلیدهای رزرو `_tokens/_timers/…` درون
 * همان ستون `variables` نگه داشته می‌شوند (متغیرهای کاربر با `_` شروع نمی‌شوند).
 * 🔒 fire اتمیک است: هر خطا → بازگردانی وضعیت پیشین (snapshot/restore).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class ProcessInstance
{
    private const STATUS_RUNNING = 'running';

    private const STATUS_COMPLETED = 'completed';

    private const STATUS_CANCELED = 'canceled';

    /** @var list<string> توکن‌های فعال (state id) */
    private array $tokens = [];

    /** @var array<string, mixed> متغیرهای کاربر (بدون کلیدهای رزرو) */
    private array $variables = [];

    /** @var list<array{id: string, due: string}> تایمرهای در انتظار */
    private array $pendingTimers = [];

    /** @var array<string, int> gatewayId → تعداد توکن رسیده (join) */
    private array $joins = [];

    /** @var list<string> شناسهٔ فرایندهای فرزند (Sub-Process) */
    private array $childIds = [];

    private int $ended = 0;

    private ?string $lastEnd = null;

    private string $status = self::STATUS_RUNNING;

    private ?\DateTimeImmutable $startedAt = null;

    private ?\DateTimeImmutable $completedAt = null;

    /**
     * @param array<string, mixed>               $variables متغیرها + کلیدهای رزرو (بازسازی از store)
     * @param array{instance: string, state: string}|null $parent پیوند والد برای Sub-Process
     *
     * @internal — ساخت فقط از راه WorkflowManager
     */
    public function __construct(
        public readonly string $id,
        private readonly ProcessDefinition $definition,
        private readonly StoreInterface $store,
        private readonly WorkflowManager $manager,
        private readonly ?DispatcherInterface $events = null,
        array $variables = [],
        private readonly ?string $workflowId = null,
        private readonly ?string $subjectType = null,
        private readonly ?string $subjectId = null,
        ?\DateTimeImmutable $startedAt = null,
        private readonly ?array $parent = null,
        string $status = self::STATUS_RUNNING,
        ?\DateTimeImmutable $completedAt = null,
    ) {
        $this->startedAt = $startedAt ?? Clock::now();
        $this->status = \in_array($status, [self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_CANCELED], true)
            ? $status
            : self::STATUS_RUNNING;
        $this->completedAt = $completedAt;
        $this->loadState($variables);
    }

    // ─── API عمومی ────────────────────────────────────────────────────────

    /**
     * همگام‌سازی مجدد با store (پس از تغییر از راه دستهٔ دیگر — Task/Timer/…).
     *
     * متدهای تغییردهنده (fire/complete/…) پیش از اجرا خودکار refresh می‌کنند؛
     * متدهای خواندنی از وضعیت حافظه استفاده می‌کنند.
     */
    public function refresh(): self
    {
        $row = $this->store->findInstance($this->id);
        if ($row === null) {
            return $this;
        }

        $status = (string) ($row['status'] ?? self::STATUS_RUNNING);
        $this->status = \in_array($status, [self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_CANCELED], true)
            ? $status
            : self::STATUS_RUNNING;
        $this->completedAt = isset($row['completed_at']) ? new \DateTimeImmutable((string) $row['completed_at']) : null;
        $this->loadState((array) ($row['variables'] ?? []));

        return $this;
    }

    /**
     * ورود اولیهٔ توکن به state شروع (task/timer/subprocess در صورت نیاز).
     *
     * @internal — فقط توسط WorkflowManager هنگام ساخت instance جدید
     */
    public function initialize(): void
    {
        if ($this->tokens !== [] || $this->status !== self::STATUS_RUNNING) {
            return;
        }

        $this->enterState($this->definition->startStateId());
        $this->persist();
        $this->checkCompletion();
    }

    /**
     * اجرای یک گذار (DOC-071 §4): guard → authorize → actions → حرکت token → history → event.
     *
     * @param array<string, mixed> $input
     *
     * @throws WorkflowException          instance در حال اجرا نیست
     * @throws IllegalTransitionException guard رد / توکن نبود / دروازه بن‌بست
     * @throws AuthorizationException     کاربر مجوز transition را ندارد
     */
    public function fire(string $transitionId, array $input = []): void
    {
        $this->refresh();   // همگام‌سازی با store (تغییرات Task/Timer از دسته‌های دیگر)
        $this->assertRunning();

        $transition = $this->definition->transition($transitionId)
            ?? throw IllegalTransitionException::unknownTransition($transitionId);

        // 🔒 دروازه‌ها داخلی‌اند — گذار خروجیِ آن‌ها فقط توسط موتور اجرا می‌شود
        if ($this->definition->isGateway($transition->from)) {
            throw IllegalTransitionException::manualGatewayFire($transition->from);
        }

        $tokenIndex = \array_search($transition->from, $this->tokens, true);
        if ($tokenIndex === false) {
            throw IllegalTransitionException::noToken($transitionId, $transition->from);
        }

        $snapshot = $this->snapshot();
        try {
            $context = new ProcessContext($this, $input);

            // 1) Guardها
            $this->checkGuards($transition, $context);
            // 2) مجوز کاربر (RBAC)
            $this->authorize($transition, $context);
            // 3) Actionها
            $this->runActions($transition, $context);

            // ورودی fire → متغیرهای فرایند (🔒 کلیدهای رزرو رد می‌شوند)
            foreach ($input as $key => $value) {
                if (!\is_string($key) || $key === '' || $key[0] === '_') {
                    continue;
                }
                $this->variables[$key] = $value;
            }

            // 4) History خودِ گذار (پیش از مسیریابی خودکار دروازه‌ها — ترتیب زمانی درست)
            $this->recordHistory($transition->from, $transition->to, $transitionId);

            // 5) حرکت Token
            \array_splice($this->tokens, (int) $tokenIndex, 1);
            $this->cancelTimersOf($transition->from);
            $this->enterState($transition->to);

            // 6) Event
            $this->dispatch(new ProcessTransitioned($this->id, $transitionId, $transition->from, $transition->to), 'workflow.transitioned', [
                'instance' => $this->id,
                'transition' => $transitionId,
                'from' => $transition->from,
                'to' => $transition->to,
            ]);

            // 7) پایان؟
            $this->persist();
            $this->checkCompletion();
        } catch (\Throwable $e) {
            $this->restore($snapshot);   // 🔒 اتمیک: شکست = بدون اثر روی state
            throw $e;
        }
    }

    /**
     * اجرای تایمر سررسیده (DOC-071 §6) — بدون guard کاربر.
     *
     * @return bool آیا تایمر اثر کرد (توکن هنوز در state مبدأ بود)
     */
    public function fireTimer(string $timerId): bool
    {
        $this->refresh();
        if ($this->status !== self::STATUS_RUNNING) {
            return false;
        }

        $timer = $this->definition->timer($timerId);
        $slot = null;
        foreach ($this->pendingTimers as $i => $pending) {
            if ($pending['id'] === $timerId) {
                $slot = $i;
                break;
            }
        }
        if ($timer === null || $slot === null) {
            return false;
        }

        $tokenIndex = \array_search($timer->on, $this->tokens, true);
        \array_splice($this->pendingTimers, $slot, 1);
        if ($tokenIndex === false) {
            $this->persist();

            return false;   // state ترک شده — تایمر بی‌اثر
        }

        $snapshot = $this->snapshot();
        try {
            $this->recordHistory($timer->on, $timer->to, $timerId);

            \array_splice($this->tokens, (int) $tokenIndex, 1);
            $this->cancelTimersOf($timer->on);
            $this->enterState($timer->to);

            $this->dispatch(new ProcessTransitioned($this->id, $timerId, $timer->on, $timer->to, 'timer'), 'workflow.transitioned', [
                'instance' => $this->id,
                'transition' => $timerId,
                'from' => $timer->on,
                'to' => $timer->to,
                'trigger' => 'timer',
            ]);

            $this->persist();
            $this->checkCompletion();
        } catch (\Throwable $e) {
            $this->restore($snapshot);
            throw $e;
        }

        return true;
    }

    /**
     * آیا گذار (بدون side effect) قابل اجراست؟ — توکن + guard + مجوز.
     *
     * @param array<string, mixed> $input
     */
    public function can(string $transitionId, array $input = []): bool
    {
        if ($this->status !== self::STATUS_RUNNING) {
            return false;
        }
        $transition = $this->definition->transition($transitionId);
        if ($transition === null || !\in_array($transition->from, $this->tokens, true)) {
            return false;
        }

        $context = new ProcessContext($this, $input);
        try {
            $this->checkGuards($transition, $context);
            $this->authorize($transition, $context);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /** گذارهای قابل‌اجرا از وضعیت فعلی. @return list<string> */
    public function availableTransitions(array $input = []): array
    {
        $available = [];
        foreach ($this->definition->transitions() as $transition) {
            if ($this->can($transition->id, $input)) {
                $available[] = $transition->id;
            }
        }

        return $available;
    }

    /** وضعیت فعلی (اولین توکن فعال؛ تکمیل‌شده → state پایانی). */
    public function currentState(): ?string
    {
        return $this->tokens[0] ?? $this->lastEnd;
    }

    /** همهٔ stateهای فعال (parallel). @return list<string> */
    public function states(): array
    {
        return $this->tokens;
    }

    /** @return array<string, mixed> متغیرهای کاربر (بدون کلیدهای رزرو) */
    public function variables(): array
    {
        return $this->variables;
    }

    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    public function setVariable(string $key, mixed $value): void
    {
        if ($key === '' || $key[0] === '_') {
            throw new \InvalidArgumentException('Variable keys starting with "_" are reserved by the workflow engine.');
        }
        $this->variables[$key] = $value;
        $this->persist();
    }

    /** تایمرهای در انتظار. @return list<array{id: string, due: string}> */
    public function pendingTimers(): array
    {
        return $this->pendingTimers;
    }

    public function definition(): ProcessDefinition
    {
        return $this->definition;
    }

    public function workflowId(): ?string
    {
        return $this->workflowId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function subjectType(): ?string
    {
        return $this->subjectType;
    }

    public function subjectId(): ?string
    {
        return $this->subjectId;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt ?? Clock::now();
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /** @return array{instance: string, state: string}|null پیوند به فرایند والد (Sub-Process) */
    public function parent(): ?array
    {
        return $this->parent;
    }

    /** @return list<string> شناسهٔ فرایندهای فرزند */
    public function children(): array
    {
        return $this->childIds;
    }

    /** کارهای این instance. @return list<Task> */
    public function tasks(?string $status = null): array
    {
        $rows = $this->store->tasksForInstance($this->id, $status);

        return \array_map(fn (array $r): Task => Task::fromRow($this->manager, $r), $rows);
    }

    /** تاریخچهٔ گذارها. @return list<array<string, mixed>> */
    public function history(): array
    {
        return $this->store->history($this->id);
    }

    /** پایان موفق فرایند. */
    public function complete(): void
    {
        $this->refresh();
        if ($this->status !== self::STATUS_RUNNING) {
            return;
        }
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = Clock::now();
        $this->tokens = [];
        $this->pendingTimers = [];
        $this->persist();

        $this->dispatch(new WorkflowCompleted($this->id, $this->definition->id, $this->lastEnd), 'workflow.completed', [
            'instance' => $this->id,
            'workflow' => $this->definition->id,
            'end_state' => $this->lastEnd,
        ]);

        // Sub-Process: پیش‌برد والد
        $this->manager->onChildCompleted($this);
    }

    /** لغو فرایند (بدون پایان منطقی). */
    public function cancel(): void
    {
        $this->refresh();
        if ($this->status !== self::STATUS_RUNNING) {
            return;
        }
        $this->status = self::STATUS_CANCELED;
        $this->completedAt = Clock::now();
        $this->tokens = [];
        $this->pendingTimers = [];
        $this->persist();

        $this->dispatch(new WorkflowCanceled($this->id, $this->definition->id), 'workflow.canceled', [
            'instance' => $this->id,
            'workflow' => $this->definition->id,
        ]);
    }

    /**
     * پیش‌برد والد پس از تکمیل زیرفرایند (token از state فرایند فرزند عبور می‌کند).
     *
     * @internal — توسط WorkflowManager::onChildCompleted
     */
    public function advanceSubProcess(string $stateId): void
    {
        $this->refresh();
        if ($this->status !== self::STATUS_RUNNING) {
            return;
        }
        $tokenIndex = \array_search($stateId, $this->tokens, true);
        if ($tokenIndex === false) {
            return;
        }

        $snapshot = $this->snapshot();
        try {
            $outgoing = $this->definition->outgoing($stateId);
            if (\count($outgoing) === 1) {
                \array_splice($this->tokens, (int) $tokenIndex, 1);
                $this->cancelTimersOf($stateId);
                $target = $outgoing[0]->to;
                $this->recordHistory($stateId, $target, $outgoing[0]->id);
                $this->enterState($target);
                $this->dispatch(new ProcessTransitioned($this->id, $outgoing[0]->id, $stateId, $target, 'subprocess'), 'workflow.transitioned', [
                    'instance' => $this->id,
                    'transition' => $outgoing[0]->id,
                    'from' => $stateId,
                    'to' => $target,
                    'trigger' => 'subprocess',
                ]);
            }
            // چند گذار خروجی → توکن در state می‌ماند تا کاربر fire کند

            $this->persist();
            $this->checkCompletion();
        } catch (\Throwable $e) {
            $this->restore($snapshot);
            throw $e;
        }
    }

    // ─── داخلی ─────────────────────────────────────────────────────────────

    /** کاربر جاری (از گارد auth — null برای تایمر/سیستم). @internal */
    public function resolveUser(): ?object
    {
        $container = WorkflowResolver::container();
        if ($container === null || !$container->bound('auth')) {
            return null;
        }

        try {
            $auth = $container->make('auth');
            if (\is_object($auth) && \method_exists($auth, 'user')) {
                $user = $auth->user();

                return \is_object($user) ? $user : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /** رویداد اطلاع به assignee (best-effort — DOC-071 §3). @internal */
    public function notifyAssignee(): void
    {
        foreach ($this->tasks('pending') as $task) {
            $this->events?->dispatch('workflow.task.notify', [
                'instance' => $this->id,
                'task' => $task->id,
                'state' => $task->stateId,
                'assignee_type' => $task->assigneeType(),
                'assignee_id' => $task->assigneeId(),
            ]);
        }
    }

    /** ورود توکن به یک state (پرداخت آنی دروازه‌ها — DOC-071 §4 گام ۴). */
    private function enterState(string $stateId): void
    {
        $state = $this->definition->state($stateId);
        if ($state === null) {
            return;
        }

        if ($state->type === StateType::End) {
            $this->ended++;
            $this->lastEnd = $stateId;

            return;
        }

        if ($state->type->isGateway()) {
            $this->handleGateway($state);

            return;
        }

        $this->tokens[] = $stateId;

        if ($state->type === StateType::UserTask) {
            $this->createTask($state);
        }

        foreach ($this->definition->timersFor($stateId) as $timer) {
            $due = Duration::add(Clock::now(), $timer->after);
            $this->pendingTimers[] = ['id' => $timer->id, 'due' => $due->format('Y-m-d H:i:s.v')];
        }

        if ($state->type === StateType::SubProcess && $state->process !== null) {
            $child = $this->manager->startChild($state->process, $this, $stateId);
            if ($child !== null) {
                $this->childIds[] = $child->id;
            }
        }
    }

    /** دروازهٔ انحصاری/موازی (fork/join). */
    private function handleGateway(StateDefinition $gateway): void
    {
        $incoming = $this->definition->incoming($gateway->id);
        $outgoing = $this->definition->outgoing($gateway->id);

        // Join: انتظار برای رسیدن همهٔ توکن‌ها
        if (\count($incoming) > 1) {
            $arrived = ($this->joins[$gateway->id] ?? 0) + 1;
            if ($arrived < \count($incoming)) {
                $this->joins[$gateway->id] = $arrived;
                $this->tokens[] = $gateway->id;   // توکن منتظر در خودِ join

                return;
            }
            unset($this->joins[$gateway->id]);

            // حذف توکن‌های منتظرِ join (هر ورودی جز ورود جاری یک توکن پارک کرده بود)
            $waiting = \count($incoming) - 1;
            for ($i = 0; $i < $waiting; $i++) {
                $idx = \array_search($gateway->id, $this->tokens, true);
                if ($idx !== false) {
                    \array_splice($this->tokens, (int) $idx, 1);
                }
            }
        }

        if ($outgoing === []) {
            throw IllegalTransitionException::gatewayStuck($gateway->id);
        }

        if ($gateway->type === StateType::ExclusiveGateway) {
            // اولین گذار با guard موفق (ترتیب = ترتیب تعریف)
            $context = new ProcessContext($this);
            foreach ($outgoing as $transition) {
                if ($this->guardsPass($transition, $context)) {
                    $this->recordHistory($gateway->id, $transition->to, $transition->id);
                    $this->enterState($transition->to);

                    return;
                }
            }

            throw IllegalTransitionException::gatewayStuck($gateway->id);
        }

        // Parallel fork: یک توکن برای هر خروجی
        foreach ($outgoing as $transition) {
            $this->recordHistory($gateway->id, $transition->to, $transition->id);
            $this->enterState($transition->to);
        }
    }

    /** @throws IllegalTransitionException */
    private function checkGuards(TransitionDefinition $transition, ProcessContext $context): void
    {
        if (!$this->guardsPass($transition, $context)) {
            throw IllegalTransitionException::guardFailed($transition->id, $transition->from);
        }
    }

    private function guardsPass(TransitionDefinition $transition, ProcessContext $context): bool
    {
        foreach ($transition->guards as $guard) {
            if (\is_string($guard)) {
                // قالب رشته‌ای (JSON): `can:<permission>`
                if (\str_starts_with($guard, 'can:') && !$context->can(\substr($guard, 4))) {
                    return false;
                }
                continue;
            }
            if (!$guard($context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 🔒 مجوز RBAC روی transition (DOC-071 §8 — «گذار غیرمجاز»).
     *
     * @throws AuthorizationException
     */
    private function authorize(TransitionDefinition $transition, ProcessContext $context): void
    {
        if ($transition->permission === null) {
            return;
        }
        if (!$context->can($transition->permission)) {
            throw new AuthorizationException(
                messageId: 'workflow.unauthorized',
                context: ['transition' => $transition->id, 'permission' => $transition->permission],
                hint: 'کاربر جاری مجوز این گذار را ندارد (RBAC).',
            );
        }
    }

    private function runActions(TransitionDefinition $transition, ProcessContext $context): void
    {
        foreach ($transition->actions as $action) {
            if (\is_string($action)) {
                // `event:<name>` → dispatch رشته‌ای
                if (\str_starts_with($action, 'event:')) {
                    $this->events?->dispatch(\substr($action, 6), ['instance' => $this->id, 'transition' => $transition->id]);
                }
                continue;
            }
            $action($context);
        }
    }

    private function createTask(StateDefinition $state): void
    {
        [$type, $assigneeId] = $this->parseAssignee($state->assignee);
        $taskId = Ulid::generate()->toString();

        $this->store->createTask([
            'id' => $taskId,
            'instance_id' => $this->id,
            'state_id' => $state->id,
            'assignee_type' => $type,
            'assignee_id' => $assigneeId,
            'status' => 'pending',
            'due_at' => null,
            'completed_at' => null,
        ]);

        $this->dispatch(new TaskCreated($taskId, $this->id, $state->id, $type, $assigneeId), 'workflow.task.created', [
            'task' => $taskId,
            'instance' => $this->id,
            'state' => $state->id,
            'assignee_type' => $type,
            'assignee_id' => $assigneeId,
        ]);
    }

    /** `role:x` / `user:1` → [type, id] — @return array{0: string|null, 1: string|null} */
    private function parseAssignee(?string $assignee): array
    {
        if ($assignee === null || $assignee === '') {
            return [null, null];
        }
        if (\str_starts_with($assignee, 'role:')) {
            return ['role', \substr($assignee, 5)];
        }
        if (\str_starts_with($assignee, 'user:')) {
            return ['user', \substr($assignee, 5)];
        }

        return ['user', $assignee];
    }

    /** لغو تایمرهای state ترک‌شده. */
    private function cancelTimersOf(string $stateId): void
    {
        $timers = $this->definition->timersFor($stateId);
        if ($timers === []) {
            return;
        }
        $ids = [];
        foreach ($timers as $timer) {
            $ids[] = $timer->id;
        }
        $this->pendingTimers = \array_values(\array_filter(
            $this->pendingTimers,
            static fn (array $t): bool => !\in_array($t['id'], $ids, true),
        ));
    }

    private function checkCompletion(): void
    {
        if ($this->status === self::STATUS_RUNNING && $this->tokens === [] && $this->ended > 0) {
            $this->complete();
        }
    }

    private function recordHistory(string $from, string $to, string $transition): void
    {
        $user = $this->resolveUser();
        $userId = null;
        if ($user !== null && \method_exists($user, 'getAuthIdentifier')) {
            $identifier = $user->getAuthIdentifier();
            if (\is_int($identifier)) {
                $userId = $identifier;
            } elseif (\is_string($identifier) && \ctype_digit($identifier)) {
                $userId = (int) $identifier;
            }
        }

        $this->store->recordHistory([
            'instance_id' => $this->id,
            'from_state' => $from,
            'to_state' => $to,
            'transition' => $transition,
            'user_id' => $userId,
            'occurred_at' => Clock::now()->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatch(object $event, string $name, array $payload): void
    {
        $this->events?->dispatch($event);
        $this->events?->dispatch($name, $payload);
    }

    // ─── وضعیت/پایداری ────────────────────────────────────────────────────

    /** @param array<string, mixed> $variables */
    private function loadState(array $variables): void
    {
        $tokens = $variables['_tokens'] ?? null;
        $this->tokens = \is_array($tokens) ? \array_values(\array_map('strval', $tokens)) : [];

        $this->pendingTimers = [];
        foreach ((array) ($variables['_timers'] ?? []) as $timer) {
            if (\is_array($timer) && isset($timer['id'], $timer['due'])) {
                $this->pendingTimers[] = ['id' => (string) $timer['id'], 'due' => (string) $timer['due']];
            }
        }

        $this->joins = [];
        foreach ((array) ($variables['_joins'] ?? []) as $gateway => $count) {
            $this->joins[(string) $gateway] = (int) $count;
        }

        $this->childIds = \array_values(\array_map('strval', (array) ($variables['_children'] ?? [])));
        $this->ended = (int) ($variables['_ended'] ?? 0);
        $this->lastEnd = isset($variables['_last_end']) ? (string) $variables['_last_end'] : null;

        foreach ($variables as $key => $value) {
            if (\is_string($key) && $key !== '' && $key[0] === '_') {
                continue;
            }
            $this->variables[(string) $key] = $value;
        }
    }

    /** @return array<string, mixed> */
    private function serializeVariables(): array
    {
        $reserved = [
            '_tokens' => $this->tokens,
            '_timers' => $this->pendingTimers,
            '_ended' => $this->ended,
        ];
        if ($this->joins !== []) {
            $reserved['_joins'] = $this->joins;
        }
        if ($this->lastEnd !== null) {
            $reserved['_last_end'] = $this->lastEnd;
        }
        if ($this->parent !== null) {
            $reserved['_parent'] = $this->parent;
        }
        if ($this->childIds !== []) {
            $reserved['_children'] = $this->childIds;
        }

        return [...$this->variables, ...$reserved];
    }

    private function persist(): void
    {
        $this->store->updateInstance($this->id, [
            'current_state' => $this->currentState() ?? '',
            'status' => $this->status,
            'variables' => $this->serializeVariables(),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s.v'),
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'tokens' => $this->tokens,
            'variables' => $this->variables,
            'timers' => $this->pendingTimers,
            'joins' => $this->joins,
            'children' => $this->childIds,
            'ended' => $this->ended,
            'lastEnd' => $this->lastEnd,
            'status' => $this->status,
            'completedAt' => $this->completedAt,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function restore(array $snapshot): void
    {
        $this->tokens = $snapshot['tokens'];
        $this->variables = $snapshot['variables'];
        $this->pendingTimers = $snapshot['timers'];
        $this->joins = $snapshot['joins'];
        $this->childIds = $snapshot['children'];
        $this->ended = $snapshot['ended'];
        $this->lastEnd = $snapshot['lastEnd'];
        $this->status = $snapshot['status'];
        $this->completedAt = $snapshot['completedAt'];
    }

    /** @throws WorkflowException */
    private function assertRunning(): void
    {
        if ($this->status !== self::STATUS_RUNNING) {
            throw WorkflowException::notRunning($this->id, $this->status);
        }
    }
}
