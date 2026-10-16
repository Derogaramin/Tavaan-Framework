<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Workflow\Events\WorkflowStarted;
use Tavaan\Engine\Workflow\Exceptions\TaskException;
use Tavaan\Engine\Workflow\Exceptions\WorkflowException;
use Tavaan\Engine\Workflow\Store\StoreInterface;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Workflow
 * WorkflowManager — ریشهٔ Facade (`Workflow::…`): define/start/instance/definitions.
 *
 * ### مثال
 * ```php
 * Workflow::define('order-approval', function (ProcessBuilder $p): void { … });
 * $instance = Workflow::start('order-approval', Order::class, (string) $order->id, ['amount' => 5_000_000]);
 * $instance->fire('submit');
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class WorkflowManager
{
    /** @var array<string, ProcessDefinition> name → آخرین تعریف بارگذاری‌شده */
    private array $definitions = [];

    /** @var array<string, ProcessDefinition> workflowRowId → تعریف */
    private array $definitionsByRowId = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly StoreInterface $store,
        private readonly array $config = [],
        private readonly ?DispatcherInterface $events = null,
        private readonly ?ContainerInterface $container = null,
    ) {
    }

    // ─── تعریف ─────────────────────────────────────────────────────────────

    /**
     * تعریف فرایند با DSL (DOC-071 §3) + persist خودکار.
     */
    public function define(string $name, \Closure $builder): ProcessDefinition
    {
        $processBuilder = new ProcessBuilder($name);
        $builder($processBuilder);

        return $this->register($processBuilder->build($this->nextVersion($name)));
    }

    /**
     * تعریف فرایند از آرایه/JSON (DOC-071 §3.1 — خروجی Designer).
     *
     * @param array<string, mixed> $data
     */
    public function defineArray(array $data): ProcessDefinition
    {
        $name = (string) ($data['id'] ?? '');
        $data['version'] = $this->nextVersion($name);

        return $this->register(ProcessDefinition::fromArray($data));
    }

    /** تعریف فرایند از رشتهٔ JSON. */
    public function defineJson(string $json): ProcessDefinition
    {
        $data = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $this->defineArray(\is_array($data) ? $data : []);
    }

    /**
     * آخرین تعریف فعال (حافظه یا store).
     *
     * @throws WorkflowException
     */
    public function definition(string $name): ProcessDefinition
    {
        if (isset($this->definitions[$name])) {
            return $this->definitions[$name];
        }

        $row = $this->store->latestWorkflow($name)
            ?? throw WorkflowException::workflowNotFound($name);

        return $this->hydrateDefinition($row);
    }

    /**
     * فهرست تعاریف فعال.
     *
     * @return list<array<string, mixed>> ردیف‌های store (name/version/is_active/…)
     */
    public function definitions(): array
    {
        return $this->store->workflows();
    }

    // ─── اجرا ──────────────────────────────────────────────────────────────

    /**
     * شروع یک instance جدید.
     *
     * @param array<string, mixed> $variables
     *
     * @throws WorkflowException تعریف نبود / سقف instance (🔒 DoS)
     */
    public function start(string $name, ?string $subjectType = null, ?string $subjectId = null, array $variables = []): ProcessInstance
    {
        $definition = $this->definition($name);
        $workflowRow = $this->store->latestWorkflow($name);
        $workflowId = $workflowRow === null ? null : (string) $workflowRow['id'];

        // 🔒 محافظ DoS: سقف instance فعال per workflow (DOC-071 §8)
        $limit = (int) ($this->config['max_active_instances'] ?? 0);
        if ($limit > 0 && $workflowId !== null && $this->store->countActiveInstances($workflowId) >= $limit) {
            throw WorkflowException::maxInstances($name, $limit);
        }

        $instance = $this->newInstance($definition, $workflowId, $subjectType, $subjectId, $variables, null);

        // ورود توکن به state شروع (task/timer اگر start نیاز داشته باشد)
        $instance->initialize();

        $this->events?->dispatch(new WorkflowStarted($instance->id, $name, $subjectType, $subjectId));
        $this->events?->dispatch('workflow.started', [
            'instance' => $instance->id,
            'workflow' => $name,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);

        return $instance;
    }

    /**
     * بارگذاری یک instance (rehydrate از store).
     *
     * @throws WorkflowException
     */
    public function instance(string $id): ProcessInstance
    {
        $row = $this->store->findInstance($id)
            ?? throw WorkflowException::processNotFound($id);

        $variables = (array) ($row['variables'] ?? []);
        $definition = $this->definitionForRow((string) ($row['workflow_id'] ?? ''));

        return new ProcessInstance(
            id: (string) $row['id'],
            definition: $definition,
            store: $this->store,
            manager: $this,
            events: $this->events,
            variables: $variables,
            workflowId: isset($row['workflow_id']) ? (string) $row['workflow_id'] : null,
            subjectType: isset($row['subject_type']) ? (string) $row['subject_type'] : null,
            subjectId: isset($row['subject_id']) ? (string) $row['subject_id'] : null,
            startedAt: isset($row['started_at']) ? new \DateTimeImmutable((string) $row['started_at']) : null,
            parent: $this->parentFromVariables($variables),
            status: (string) ($row['status'] ?? 'running'),
            completedAt: isset($row['completed_at']) ? new \DateTimeImmutable((string) $row['completed_at']) : null,
        );
    }

    /**
     * کارهای یک کاربر (DOC-071 §7) — inbox شامل:
     * کارهای مستقیم (user:X) + کارهای نقشِ قابل‌تصاحب (role:R که کاربر R دارد).
     *
     * @return list<Task>
     */
    public function tasksFor(string|int $assigneeId, ?string $status = 'pending'): array
    {
        $assigneeId = (string) $assigneeId;
        $rows = [];

        foreach ($this->store->tasksForAssignee($assigneeId, $status) as $row) {
            if ((string) ($row['assignee_type'] ?? '') === 'user') {
                $rows[(string) $row['id']] = $row;
            }
        }

        foreach ($this->userRoles($assigneeId) as $role) {
            foreach ($this->store->tasksForAssignee($role, $status) as $row) {
                if ((string) ($row['assignee_type'] ?? '') === 'role') {
                    $rows[(string) $row['id']] = $row;
                }
            }
        }

        return \array_map(fn (array $r): Task => Task::fromRow($this, $r), \array_values($rows));
    }

    /**
     * یک کار بر اساس شناسه.
     *
     * @throws TaskException
     */
    public function task(string $taskId): Task
    {
        $row = $this->store->findTask($taskId)
            ?? throw TaskException::notFound($taskId);

        return Task::fromRow($this, $row);
    }

    public function store(): StoreInterface
    {
        return $this->store;
    }

    public function timerProcessor(): TimerProcessor
    {
        return new TimerProcessor($this, (int) ($this->config['timers']['batch_size'] ?? 100));
    }

    /**
     * بررسی نقش کاربر برای claim (با RBAC — fail-closed اگر RBAC نباشد).
     */
    public function userHasRole(string|int $userId, string $role): bool
    {
        $container = $this->container ?? WorkflowResolver::container();
        if ($container === null) {
            return false;
        }

        try {
            if ($container->bound(\Tavaan\Engine\Rbac\RoleManager::class)) {
                $roles = $container->make(\Tavaan\Engine\Rbac\RoleManager::class);
                if ($roles instanceof \Tavaan\Engine\Rbac\RoleManager) {
                    return $roles->hasRole($userId, $role);
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * 🔒 mask متغیرهای حساس برای نمایش/لاگ (DOC-071 §8 — «نشت متغیرها»).
     *
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    public function maskVariables(array $variables): array
    {
        $masked = (array) ($this->config['masked_variables'] ?? []);
        foreach ($variables as $key => $value) {
            foreach ($masked as $pattern) {
                if (\is_string($pattern) && $pattern !== '' && \str_contains(\strtolower((string) $key), \strtolower($pattern))) {
                    $variables[$key] = '***';
                    break;
                }
            }
        }

        return $variables;
    }

    /**
     * @internal — ساخت/شروع زیرفرایند توسط ProcessInstance.
     */
    public function startChild(string $name, ProcessInstance $parent, string $stateId): ?ProcessInstance
    {
        try {
            $definition = $this->definition($name);
        } catch (WorkflowException) {
            return null;   // تعریف فرزند نبود — والد در state می‌ماند (قابل fire دستی)
        }

        $workflowRow = $this->store->latestWorkflow($name);
        $child = $this->newInstance(
            $definition,
            $workflowRow === null ? null : (string) $workflowRow['id'],
            $parent->subjectType(),
            $parent->subjectId(),
            $parent->variables(),
            ['instance' => $parent->id, 'state' => $stateId],
        );
        $child->initialize();

        $this->events?->dispatch(new WorkflowStarted($child->id, $name, $parent->subjectType(), $parent->subjectId()));

        return $child;
    }

    /**
     * نقش‌های کاربر (RBAC — best-effort؛ بدون RBAC → خالی).
     *
     * @return list<string>
     */
    public function userRoles(string|int $userId): array
    {
        $container = $this->container ?? WorkflowResolver::container();
        if ($container === null) {
            return [];
        }

        try {
            if ($container->bound(\Tavaan\Engine\Rbac\RoleManager::class)) {
                $roles = $container->make(\Tavaan\Engine\Rbac\RoleManager::class);
                if ($roles instanceof \Tavaan\Engine\Rbac\RoleManager) {
                    return $roles->rolesOf($userId);
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return [];
    }

    /**
     * @internal — پس از تکمیل فرزند، والد از state زیرفرایند عبور می‌کند.
     */
    public function onChildCompleted(ProcessInstance $child): void
    {
        $parent = $child->parent();
        if ($parent === null) {
            return;
        }

        try {
            $parentInstance = $this->instance($parent['instance']);
        } catch (WorkflowException) {
            return;
        }
        if (!$parentInstance->isRunning()) {
            return;
        }

        try {
            $parentInstance->advanceSubProcess($parent['state']);
        } catch (\Throwable) {
            // 🔒 خطای پیش‌برد والد هرگز تکمیل فرزند را rollback نمی‌کند
        }
    }

    // ─── داخلی ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed>               $variables
     * @param array{instance: string, state: string}|null $parent
     */
    private function newInstance(
        ProcessDefinition $definition,
        ?string $workflowId,
        ?string $subjectType,
        ?string $subjectId,
        array $variables,
        ?array $parent,
    ): ProcessInstance {
        $id = Ulid::generate()->toString();
        $now = Clock::now()->format('Y-m-d H:i:s.v');

        if ($parent !== null) {
            $variables['_parent'] = $parent;
        }

        $this->store->createInstance([
            'id' => $id,
            'workflow_id' => $workflowId ?? '',
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'current_state' => $definition->startStateId(),
            'status' => 'running',
            'variables' => $variables,
            'started_at' => $now,
            'completed_at' => null,
        ]);

        return new ProcessInstance(
            id: $id,
            definition: $definition,
            store: $this->store,
            manager: $this,
            events: $this->events,
            variables: $variables,
            workflowId: $workflowId,
            subjectType: $subjectType,
            subjectId: $subjectId,
            startedAt: new \DateTimeImmutable($now),
            parent: $parent,
        );
    }

    private function register(ProcessDefinition $definition): ProcessDefinition
    {
        $rowId = $this->store->saveWorkflow($definition);
        $this->definitions[$definition->id] = $definition;
        $this->definitionsByRowId[$rowId] = $definition;

        return $definition;
    }

    private function nextVersion(string $name): int
    {
        $existing = $this->store->latestWorkflow($name);
        if ($existing !== null) {
            return (int) $existing['version'] + 1;
        }
        foreach ($this->store->workflows() as $row) {
            if (($row['name'] ?? '') === $name) {
                return (int) ($row['version'] ?? 1) + 1;
            }
        }

        return 1;
    }

    /** @param array<string, mixed> $row */
    private function hydrateDefinition(array $row): ProcessDefinition
    {
        $rowId = (string) ($row['id'] ?? '');
        if (isset($this->definitionsByRowId[$rowId])) {
            return $this->definitionsByRowId[$rowId];
        }

        $definition = ProcessDefinition::fromArray((array) ($row['definition'] ?? []));
        $this->definitions[(string) $row['name']] = $definition;
        if ($rowId !== '') {
            $this->definitionsByRowId[$rowId] = $definition;
        }

        return $definition;
    }

    private function definitionForRow(string $rowId): ProcessDefinition
    {
        if (isset($this->definitionsByRowId[$rowId])) {
            return $this->definitionsByRowId[$rowId];
        }

        $row = $rowId === '' ? null : $this->store->workflow($rowId);
        if ($row === null) {
            throw WorkflowException::workflowNotFound($rowId);
        }

        return $this->hydrateDefinition($row);
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @return array{instance: string, state: string}|null
     */
    private function parentFromVariables(array $variables): ?array
    {
        $parent = $variables['_parent'] ?? null;
        if (\is_array($parent) && isset($parent['instance'], $parent['state'])) {
            return ['instance' => (string) $parent['instance'], 'state' => (string) $parent['state']];
        }

        return null;
    }
}
