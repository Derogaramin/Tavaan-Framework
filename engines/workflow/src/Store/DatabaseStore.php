<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Store;

use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Workflow\ProcessDefinition;

/**
 * تَوان | Tavaan — Workflow
 * DatabaseStore — ذخیره‌ساز SQL (جداول DOC-071 §5) با Query Builder (prepared — 🔒 بدون الحاق).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class DatabaseStore implements StoreInterface
{
    /**
     * @param array{workflows?: string, instances?: string, tasks?: string, history?: string} $tables
     */
    public function __construct(
        private readonly ConnectionManager $db,
        private readonly array $tables = [],
    ) {
    }

    private function t(string $key, string $default): string
    {
        return (string) ($this->tables[$key] ?? $default);
    }

    private function workflowsTable(): string
    {
        return $this->t('workflows', 'tvn_workflows');
    }

    private function instancesTable(): string
    {
        return $this->t('instances', 'tvn_process_instances');
    }

    private function tasksTable(): string
    {
        return $this->t('tasks', 'tvn_process_tasks');
    }

    private function historyTable(): string
    {
        return $this->t('history', 'tvn_process_history');
    }

    public function saveWorkflow(ProcessDefinition $definition): string
    {
        $json = \json_encode($definition->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // نسخه‌های قبلی غیرفعال (instanceهای در حال اجرا workflow_id خود را نگه می‌دارند)
        $this->db->table($this->workflowsTable())->where('name', $definition->id)->update(['is_active' => 0]);

        $id = \Tavaan\Support\Ulid::generate()->toString();
        $this->db->table($this->workflowsTable())->insert([
            'id' => $id,
            'name' => $definition->id,
            'version' => $definition->version,
            'definition' => $json,
            'is_active' => 1,
            'created_at' => \Tavaan\Support\Time\Clock::now()->format('Y-m-d H:i:s.v'),
        ]);

        return $id;
    }

    public function workflow(string $id): ?array
    {
        $row = $this->db->table($this->workflowsTable())->where('id', $id)->first();

        return $row === null ? null : $this->hydrateWorkflow($row);
    }

    public function latestWorkflow(string $name): ?array
    {
        $row = $this->db->table($this->workflowsTable())
            ->where('name', $name)
            ->where('is_active', 1)
            ->orderByDesc('version')
            ->first();

        return $row === null ? null : $this->hydrateWorkflow($row);
    }

    public function workflows(): array
    {
        $rows = $this->db->table($this->workflowsTable())->where('is_active', 1)->orderBy('name')->orderByDesc('version')->get();
        $byName = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            // اولین رخداد هر نام = بالاترین نسخه (orderByDesc)
            $byName[$name] ??= $this->hydrateWorkflow($row);
        }

        return \array_values($byName);
    }

    public function createInstance(array $row): void
    {
        $this->db->table($this->instancesTable())->insert($this->serializeInstance($row));
    }

    public function updateInstance(string $id, array $changes): void
    {
        $this->db->table($this->instancesTable())->where('id', $id)->update($this->serializeInstance($changes));
    }

    public function findInstance(string $id): ?array
    {
        $row = $this->db->table($this->instancesTable())->where('id', $id)->first();

        return $row === null ? null : $this->hydrateInstance($row);
    }

    public function countActiveInstances(string $workflowId): int
    {
        return (int) $this->db->table($this->instancesTable())
            ->where('workflow_id', $workflowId)
            ->where('status', 'running')
            ->count();
    }

    public function runningInstances(int $limit = 500): array
    {
        $rows = $this->db->table($this->instancesTable())
            ->where('status', 'running')
            ->orderBy('started_at')
            ->limit($limit)
            ->get();

        return \array_map(fn (array $r): array => $this->hydrateInstance($r), $rows);
    }

    public function recordHistory(array $row): void
    {
        $this->db->table($this->historyTable())->insert($row);
    }

    public function history(string $instanceId): array
    {
        return $this->db->table($this->historyTable())
            ->where('instance_id', $instanceId)
            ->orderBy('id')
            ->get();
    }

    public function createTask(array $row): void
    {
        $this->db->table($this->tasksTable())->insert($row);
    }

    public function updateTask(string $id, array $changes): void
    {
        $this->db->table($this->tasksTable())->where('id', $id)->update($changes);
    }

    public function findTask(string $id): ?array
    {
        return $this->db->table($this->tasksTable())->where('id', $id)->first();
    }

    public function tasksForInstance(string $instanceId, ?string $status = null): array
    {
        $query = $this->db->table($this->tasksTable())->where('instance_id', $instanceId)->orderBy('id');
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function tasksForAssignee(string $assigneeId, ?string $status = null): array
    {
        $query = $this->db->table($this->tasksTable())->where('assignee_id', $assigneeId)->orderBy('id');
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function serializeInstance(array $row): array
    {
        if (\array_key_exists('variables', $row) && \is_array($row['variables'])) {
            $row['variables'] = \json_encode($row['variables'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrateInstance(array $row): array
    {
        if (isset($row['variables']) && \is_string($row['variables'])) {
            $decoded = \json_decode($row['variables'], true);
            $row['variables'] = \is_array($decoded) ? $decoded : [];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrateWorkflow(array $row): array
    {
        if (isset($row['definition']) && \is_string($row['definition'])) {
            $decoded = \json_decode($row['definition'], true);
            $row['definition'] = \is_array($decoded) ? $decoded : [];
        }

        return $row;
    }
}
