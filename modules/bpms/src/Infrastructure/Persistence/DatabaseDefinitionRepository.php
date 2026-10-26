<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DatabaseDefinitionRepository — آداپتر تعاریف (bpms_process_definitions).
 */

namespace Modules\Bpms\Infrastructure\Persistence;

use Modules\Bpms\Domain\Entities\ProcessDefinition;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class DatabaseDefinitionRepository implements DefinitionRepositoryInterface
{
    public function save(ProcessDefinition $definition): void
    {
        $row = $this->toRow($definition);
        $exists = DB::table('bpms_process_definitions')->where('id', $definition->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at'], $row['created_by'], $row['key'], $row['version']);
            DB::table('bpms_process_definitions')->where('id', $definition->id())->update($row);

            return;
        }

        DB::table('bpms_process_definitions')->insert($row);
    }

    public function findById(string $id): ?ProcessDefinition
    {
        $row = DB::table('bpms_process_definitions')->where('id', $id)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findActiveByKey(string $key): ?ProcessDefinition
    {
        $row = DB::table('bpms_process_definitions')
            ->where('key', $key)
            ->where('is_active', true)
            ->where('status', 'published')
            ->orderByDesc('version')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function latestVersionOf(string $key): ?ProcessDefinition
    {
        $row = DB::table('bpms_process_definitions')
            ->where('key', $key)
            ->orderByDesc('version')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['key']) && (string) $filters['key'] !== '') {
                $q->where('key', (string) $filters['key']);
            }
            if (isset($filters['status']) && (string) $filters['status'] !== '') {
                $q->where('status', (string) $filters['status']);
            }
            if (isset($filters['active'])) {
                $q->where('is_active', (bool) $filters['active']);
            }
            if (isset($filters['search']) && (string) $filters['search'] !== '') {
                $like = '%' . (string) $filters['search'] . '%';
                $q->where('name', 'LIKE', $like);
            }
        };

        $count = DB::table('bpms_process_definitions');
        $rows = DB::table('bpms_process_definitions');
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('updated_at')->orderByDesc('created_at')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(fn (array $row): ProcessDefinition => $this->hydrate($row), $data),
            'total' => $total,
        ];
    }

    public function versionsOf(string $key): array
    {
        $rows = DB::table('bpms_process_definitions')
            ->where('key', $key)
            ->orderByDesc('version')
            ->get();

        return \array_map(fn (array $row): ProcessDefinition => $this->hydrate($row), $rows);
    }

    public function deactivateOtherVersions(string $key, string $exceptId, string $updatedAt): void
    {
        DB::table('bpms_process_definitions')
            ->where('key', $key)
            ->where('id', '!=', $exceptId)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => $updatedAt]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ProcessDefinition
    {
        $definition = $row['definition'] ?? [];
        if (\is_string($definition)) {
            $decoded = \json_decode($definition, true);
            $definition = \is_array($decoded) ? $decoded : [];
        }
        $forms = $row['form_schema'] ?? [];
        if (\is_string($forms)) {
            $decoded = \json_decode($forms, true);
            $forms = \is_array($decoded) ? $decoded : [];
        }
        $row['definition'] = $definition;
        $row['form_schema'] = $forms;

        return ProcessDefinition::fromRow($row);
    }

    /** @return array<string, mixed> */
    private function toRow(ProcessDefinition $definition): array
    {
        $forms = [];
        foreach ($definition->formSchemas() as $id => $schema) {
            $forms[$id] = $schema->toArray();
        }

        return [
            'id' => $definition->id(),
            'key' => $definition->key(),
            'name' => $definition->name(),
            'version' => $definition->version(),
            'definition' => (string) \json_encode($definition->model()->toArray(), JSON_UNESCAPED_UNICODE),
            'form_schema' => (string) \json_encode($forms, JSON_UNESCAPED_UNICODE),
            'status' => $definition->status()->value,
            'is_active' => $definition->isActive(),
            'description' => $definition->description(),
            'created_by' => $definition->createdBy(),
            'created_at' => $definition->createdAt(),
            'updated_at' => $definition->updatedAt(),
        ];
    }
}
