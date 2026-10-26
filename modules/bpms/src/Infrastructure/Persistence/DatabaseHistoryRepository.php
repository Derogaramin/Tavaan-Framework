<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DatabaseHistoryRepository — آداپتر تاریخچه (bpms_history — append-only).
 */

namespace Modules\Bpms\Infrastructure\Persistence;

use Modules\Bpms\Domain\Entities\HistoryEntry;
use Modules\Bpms\Domain\Repositories\HistoryRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class DatabaseHistoryRepository implements HistoryRepositoryInterface
{
    public function record(HistoryEntry $entry): void
    {
        DB::table('bpms_history')->insert([
            'instance_id' => $entry->instanceId,
            'from_state' => $entry->fromState,
            'to_state' => $entry->toState,
            'transition' => $entry->transition,
            'actor_id' => $entry->actorId,
            'trigger' => $entry->trigger,
            'occurred_at' => $entry->occurredAt,
            'duration_ms' => $entry->durationMs,
        ]);
    }

    public function forInstance(string $instanceId): array
    {
        $rows = DB::table('bpms_history')
            ->where('instance_id', $instanceId)
            ->orderBy('id')
            ->get();

        return \array_map(static fn (array $row): HistoryEntry => HistoryEntry::fromRow($row), $rows);
    }

    public function rawForMonitoring(?string $definitionKey = null, int $limit = 20000): array
    {
        $query = DB::table('bpms_history')
            ->select('id', 'instance_id', 'from_state', 'to_state', 'transition', 'actor_id', 'trigger', 'occurred_at', 'duration_ms');

        if ($definitionKey !== null && $definitionKey !== '') {
            $instanceIds = DB::table('bpms_process_instances')
                ->select('id')
                ->where('definition_key', $definitionKey)
                ->limit(10000)
                ->get();
            $ids = \array_map(static fn (array $r): string => (string) ($r['id'] ?? ''), $instanceIds);
            $query->whereIn('instance_id', $ids === [] ? [''] : $ids);
        }

        return $query->orderByDesc('occurred_at')->limit(\max(1, $limit))->get();
    }
}
