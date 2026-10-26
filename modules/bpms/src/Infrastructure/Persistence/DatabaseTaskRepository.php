<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DatabaseTaskRepository — آداپتر کارهای انسانی (bpms_tasks).
 */

namespace Modules\Bpms\Infrastructure\Persistence;

use Modules\Bpms\Domain\Entities\Task;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class DatabaseTaskRepository implements TaskRepositoryInterface
{
    public function create(Task $task): void
    {
        DB::table('bpms_tasks')->insert($this->toRow($task, withId: true));
    }

    public function update(Task $task): void
    {
        DB::table('bpms_tasks')->where('id', $task->id())->update($this->toRow($task, withId: false));
    }

    public function claimAtomic(string $taskId, int $userId, string $claimedAt): bool
    {
        // 🔒 اتمیک: فقط pending (بدون claim قبلی) یا claim خود کاربر
        $affected = DB::table('bpms_tasks')
            ->where('id', $taskId)
            ->whereIn('status', ['pending'])
            ->update([
                'status' => 'claimed',
                'assignee_type' => 'user',
                'assignee_id' => (string) $userId,
                'claimed_by' => $userId,
                'claimed_at' => $claimedAt,
            ]);

        return $affected > 0;
    }

    public function findById(string $id): ?Task
    {
        $row = DB::table('bpms_tasks')->where('id', $id)->first();

        return $row === null ? null : Task::fromRow($this->decode($row));
    }

    public function forInstance(string $instanceId, ?string $status = null): array
    {
        $query = DB::table('bpms_tasks')->where('instance_id', $instanceId);
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->orderBy('created_at')->get();

        return \array_map(fn (array $row): Task => Task::fromRow($this->decode($row)), $rows);
    }

    public function inboxFor(int $userId, array $roles, int $limit = 200): array
    {
        $userIdStr = (string) $userId;

        // کارهای مستقیم (user/initiator با شناسهٔ کاربر)
        $direct = DB::table('bpms_tasks')
            ->whereIn('status', ['pending', 'claimed'])
            ->whereIn('assignee_type', ['user', 'initiator'])
            ->where('assignee_id', $userIdStr)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $byRole = [];
        if ($roles !== []) {
            $byRole = DB::table('bpms_tasks')
                ->whereIn('status', ['pending'])
                ->whereIn('assignee_type', ['role', 'group'])
                ->whereIn('assignee_id', $roles)
                ->orderBy('created_at')
                ->limit($limit)
                ->get();
        }

        // کارهای anyone (صف عمومی)
        $anyone = DB::table('bpms_tasks')
            ->whereIn('status', ['pending'])
            ->where('assignee_type', 'anyone')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $merged = [];
        foreach ([...$direct, ...$byRole, ...$anyone] as $row) {
            $row = $this->decode($row);
            $id = (string) ($row['id'] ?? '');
            if ($id !== '' && !isset($merged[$id])) {
                $merged[$id] = Task::fromRow($row);
            }
        }

        return \array_values($merged);
    }

    public function overdueOpen(string $nowIso, int $limit = 100): array
    {
        $rows = DB::table('bpms_tasks')
            ->whereIn('status', ['pending', 'claimed'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $nowIso)
            ->whereNull('escalated_at')
            ->orderBy('due_at')
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(fn (array $row): Task => Task::fromRow($this->decode($row)), $rows);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['status']) && (string) $filters['status'] !== '') {
                $q->where('status', (string) $filters['status']);
            }
            if (isset($filters['instance_id']) && (string) $filters['instance_id'] !== '') {
                $q->where('instance_id', (string) $filters['instance_id']);
            }
            if (isset($filters['assignee_id']) && (string) $filters['assignee_id'] !== '') {
                $q->where('assignee_id', (string) $filters['assignee_id']);
            }
            if (isset($filters['overdue_now']) && (string) $filters['overdue_now'] !== '') {
                $q->whereIn('status', ['pending', 'claimed'])
                    ->whereNotNull('due_at')
                    ->where('due_at', '<=', (string) $filters['overdue_now']);
            }
        };

        $count = DB::table('bpms_tasks');
        $rows = DB::table('bpms_tasks');
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('created_at')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(fn (array $row): Task => Task::fromRow($this->decode($row)), $data),
            'total' => $total,
        ];
    }

    public function rawForMonitoring(?string $definitionKey = null, int $limit = 5000): array
    {
        $query = DB::table('bpms_tasks')
            ->select('id', 'instance_id', 'state_id', 'status', 'assignee_type', 'assignee_id', 'due_at', 'completed_at', 'escalated_at', 'created_at');

        if ($definitionKey !== null && $definitionKey !== '') {
            // join نرم (بدون FK بین‌ماژولی — همان جدول ماژول) با instance
            $instanceIds = DB::table('bpms_process_instances')
                ->select('id')
                ->where('definition_key', $definitionKey)
                ->limit(10000)
                ->get();
            $ids = \array_map(static fn (array $r): string => (string) ($r['id'] ?? ''), $instanceIds);
            $query->whereIn('instance_id', $ids === [] ? [''] : $ids);
        }

        return $query->orderByDesc('created_at')->limit(\max(1, $limit))->get();
    }

    public function cancelOpenForInstance(string $instanceId): int
    {
        return DB::table('bpms_tasks')
            ->where('instance_id', $instanceId)
            ->whereIn('status', ['pending', 'claimed'])
            ->update(['status' => 'canceled']);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function decode(array $row): array
    {
        $formData = $row['form_data'] ?? [];
        if (\is_string($formData)) {
            $decoded = \json_decode($formData, true);
            $formData = \is_array($decoded) ? $decoded : [];
        }
        $row['form_data'] = $formData;

        return $row;
    }

    /** @return array<string, mixed> */
    private function toRow(Task $task, bool $withId): array
    {
        $row = [
            'instance_id' => $task->instanceId(),
            'state_id' => $task->stateId(),
            'title' => $task->title(),
            'assignee_type' => $task->assigneeType()->value,
            'assignee_id' => $task->assigneeId(),
            'form_id' => $task->formId(),
            'form_data' => (string) \json_encode($task->formData(), JSON_UNESCAPED_UNICODE),
            'status' => $task->status()->value,
            'priority' => $task->priority()->value,
            'due_at' => $task->dueAt(),
            'claimed_by' => $task->claimedBy(),
            'completed_by' => $task->completedBy(),
            'comment' => $task->comment(),
            'created_at' => $task->createdAt(),
            'claimed_at' => $task->claimedAt(),
            'completed_at' => $task->completedAt(),
            'escalated_at' => $task->escalatedAt(),
            'escalated_to' => $task->escalatedTo(),
        ];
        if ($withId) {
            $row = ['id' => $task->id(), ...$row];
        }

        return $row;
    }
}
