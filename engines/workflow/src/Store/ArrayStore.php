<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Store;

use Tavaan\Engine\Workflow\ProcessDefinition;

/**
 * تَوان | Tavaan — Workflow
 * ArrayStore — ذخیره‌ساز درون‌حافظه‌ای (تست/اجرای بدون DB).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class ArrayStore implements StoreInterface
{
    /** @var list<array<string, mixed>> */
    private array $workflows = [];

    /** @var array<string, array<string, mixed>> */
    private array $instances = [];

    /** @var list<array<string, mixed>> */
    private array $history = [];

    /** @var array<string, array<string, mixed>> */
    private array $tasks = [];

    public function saveWorkflow(ProcessDefinition $definition): string
    {
        $id = \Tavaan\Support\Ulid::generate()->toString();
        foreach ($this->workflows as $i => $existing) {
            if ($existing['name'] === $definition->id) {
                $this->workflows[$i]['is_active'] = false;
            }
        }
        $this->workflows[] = [
            'id' => $id,
            'name' => $definition->id,
            'version' => $definition->version,
            'definition' => $definition->toArray(),
            'is_active' => true,
        ];

        return $id;
    }

    public function workflow(string $id): ?array
    {
        foreach ($this->workflows as $row) {
            if ((string) $row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    public function latestWorkflow(string $name): ?array
    {
        $best = null;
        foreach ($this->workflows as $row) {
            if ($row['name'] === $name && $row['is_active']
                && ($best === null || (int) $row['version'] > (int) $best['version'])) {
                $best = $row;
            }
        }

        return $best;
    }

    public function workflows(): array
    {
        return \array_values(\array_filter(
            $this->workflows,
            static fn (array $r): bool => (bool) $r['is_active'],
        ));
    }

    public function createInstance(array $row): void
    {
        $this->instances[(string) $row['id']] = $row;
    }

    public function updateInstance(string $id, array $changes): void
    {
        if (isset($this->instances[$id])) {
            $this->instances[$id] = [...$this->instances[$id], ...$changes];
        }
    }

    public function findInstance(string $id): ?array
    {
        return $this->instances[$id] ?? null;
    }

    public function countActiveInstances(string $workflowId): int
    {
        $count = 0;
        foreach ($this->instances as $row) {
            if (($row['status'] ?? '') === 'running' && (string) ($row['workflow_id'] ?? '') === $workflowId) {
                $count++;
            }
        }

        return $count;
    }

    public function runningInstances(int $limit = 500): array
    {
        $rows = [];
        foreach ($this->instances as $row) {
            if (($row['status'] ?? '') === 'running') {
                $rows[] = $row;
                if (\count($rows) >= $limit) {
                    break;
                }
            }
        }

        return $rows;
    }

    public function recordHistory(array $row): void
    {
        $this->history[] = $row;
    }

    public function history(string $instanceId): array
    {
        return \array_values(\array_filter(
            $this->history,
            static fn (array $r): bool => (string) ($r['instance_id'] ?? '') === $instanceId,
        ));
    }

    public function createTask(array $row): void
    {
        $this->tasks[(string) $row['id']] = $row;
    }

    public function updateTask(string $id, array $changes): void
    {
        if (isset($this->tasks[$id])) {
            $this->tasks[$id] = [...$this->tasks[$id], ...$changes];
        }
    }

    public function findTask(string $id): ?array
    {
        return $this->tasks[$id] ?? null;
    }

    public function tasksForInstance(string $instanceId, ?string $status = null): array
    {
        return \array_values(\array_filter(
            $this->tasks,
            static fn (array $r): bool => (string) ($r['instance_id'] ?? '') === $instanceId
                && ($status === null || (string) ($r['status'] ?? '') === $status),
        ));
    }

    public function tasksForAssignee(string $assigneeId, ?string $status = null): array
    {
        return \array_values(\array_filter(
            $this->tasks,
            static fn (array $r): bool => (string) ($r['assignee_id'] ?? '') === $assigneeId
                && ($status === null || (string) ($r['status'] ?? '') === $status),
        ));
    }
}
