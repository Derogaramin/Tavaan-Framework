<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * DatabaseActivityRepository — آداپتر فعالیت (crm_activities).
 */

namespace Modules\Crm\Infrastructure\Persistence;

use Modules\Crm\Domain\Entities\Activity;
use Modules\Crm\Domain\Repositories\ActivityRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class DatabaseActivityRepository implements ActivityRepositoryInterface
{
    public function save(Activity $activity): void
    {
        $row = $activity->toRow();
        $exists = DB::table('crm_activities')->where('id', $activity->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('crm_activities')->where('id', $activity->id())->update($row);
        } else {
            DB::table('crm_activities')->insert($row);
        }
    }

    public function findById(string $id): ?Activity
    {
        $row = DB::table('crm_activities')->where('id', $id)->first();

        return $row === null ? null : Activity::fromRow($row);
    }

    public function forSubject(?string $contactId = null, ?string $leadId = null, ?string $opportunityId = null, ?string $ticketId = null, int $limit = 200): array
    {
        $query = DB::table('crm_activities');
        if ($contactId !== null) {
            $query->where('contact_id', $contactId);
        }
        if ($leadId !== null) {
            $query->where('lead_id', $leadId);
        }
        if ($opportunityId !== null) {
            $query->where('opportunity_id', $opportunityId);
        }
        if ($ticketId !== null) {
            $query->where('ticket_id', $ticketId);
        }

        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->limit(\max(1, $limit))->get();

        return \array_map(static fn (array $row): Activity => Activity::fromRow($row), $rows);
    }

    public function countFor(?string $contactId = null, ?string $leadId = null): int
    {
        $query = DB::table('crm_activities');
        if ($contactId !== null && $leadId !== null) {
            $query->where(static function (mixed $q) use ($contactId, $leadId): void {
                $q->where('contact_id', $contactId)->orWhere('lead_id', $leadId);
            });
        } elseif ($contactId !== null) {
            $query->where('contact_id', $contactId);
        } elseif ($leadId !== null) {
            $query->where('lead_id', $leadId);
        } else {
            return 0;
        }

        return $query->count();
    }

    public function dueFor(int $userId, bool $includeOverdue = true, int $limit = 100): array
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        $query = DB::table('crm_activities')
            ->where('user_id', $userId)
            ->where('activity_type', 'task')
            ->whereNull('completed_at')
            ->whereNotNull('due_at');

        if (!$includeOverdue) {
            $query->where('due_at', '>=', $now);
        }

        $rows = $query->orderBy('due_at')->limit(\max(1, $limit))->get();

        return \array_map(static fn (array $row): Activity => Activity::fromRow($row), $rows);
    }
}
