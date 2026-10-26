<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DatabaseInstanceRepository — آداپتر نمونه‌های فرایند (bpms_process_instances).
 */

namespace Modules\Bpms\Infrastructure\Persistence;

use Modules\Bpms\Domain\Entities\ProcessInstance;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class DatabaseInstanceRepository implements InstanceRepositoryInterface
{
    public function create(ProcessInstance $instance): void
    {
        DB::table('bpms_process_instances')->insert($this->toRow($instance, withId: true));
    }

    public function update(ProcessInstance $instance): void
    {
        DB::table('bpms_process_instances')
            ->where('id', $instance->id())
            ->update($this->toRow($instance, withId: false));
    }

    public function findById(string $id): ?ProcessInstance
    {
        $row = DB::table('bpms_process_instances')->where('id', $id)->first();

        return $row === null ? null : ProcessInstance::fromRow($this->decodeVariables($row));
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['status']) && (string) $filters['status'] !== '') {
                $q->where('status', (string) $filters['status']);
            }
            if (isset($filters['definition_key']) && (string) $filters['definition_key'] !== '') {
                $q->where('definition_key', (string) $filters['definition_key']);
            }
            if (isset($filters['initiator_id']) && $filters['initiator_id'] !== null) {
                $q->where('initiator_id', (int) $filters['initiator_id']);
            }
            if (isset($filters['search']) && (string) $filters['search'] !== '') {
                $q->where('business_key', 'LIKE', '%' . (string) $filters['search'] . '%');
            }
        };

        $count = DB::table('bpms_process_instances');
        $rows = DB::table('bpms_process_instances');
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('started_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(
                static fn (array $row): ProcessInstance => ProcessInstance::fromRow(self::decodeVariablesStatic($row)),
                $data,
            ),
            'total' => $total,
        ];
    }

    public function rawForMonitoring(?string $definitionKey = null, int $limit = 5000): array
    {
        $query = DB::table('bpms_process_instances')
            ->select('id', 'definition_key', 'status', 'started_at', 'completed_at');
        if ($definitionKey !== null && $definitionKey !== '') {
            $query->where('definition_key', $definitionKey);
        }

        return $query->orderByDesc('started_at')->limit(\max(1, $limit))->get();
    }

    public function countActiveForDefinition(string $definitionId): int
    {
        return DB::table('bpms_process_instances')
            ->where('definition_id', $definitionId)
            ->where('status', 'running')
            ->count();
    }

    /** @return array<string, mixed> */
    private function toRow(ProcessInstance $instance, bool $withId): array
    {
        $row = [
            'definition_id' => $instance->definitionId(),
            'definition_key' => $instance->definitionKey(),
            'business_key' => $instance->businessKey(),
            'initiator_id' => $instance->initiatorId(),
            'current_state' => $instance->currentState(),
            'status' => $instance->status()->value,
            'variables' => (string) \json_encode($instance->serializeVariables(), JSON_UNESCAPED_UNICODE),
            'workflow_instance_id' => $instance->workflowInstanceId(),
            'started_at' => $instance->startedAt(),
            'completed_at' => $instance->completedAt(),
        ];
        if ($withId) {
            $row = ['id' => $instance->id(), ...$row];
        }

        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function decodeVariables(array $row): array
    {
        return self::decodeVariablesStatic($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function decodeVariablesStatic(array $row): array
    {
        $variables = $row['variables'] ?? [];
        if (\is_string($variables)) {
            $decoded = \json_decode($variables, true);
            $variables = \is_array($decoded) ? $decoded : [];
        }
        $row['variables'] = $variables;

        return $row;
    }
}
