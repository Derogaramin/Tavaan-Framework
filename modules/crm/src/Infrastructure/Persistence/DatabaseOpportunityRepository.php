<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * DatabaseOpportunityRepository — آداپتر فرصت فروش (crm_opportunities).
 */

namespace Modules\Crm\Infrastructure\Persistence;

use Modules\Crm\Domain\Entities\Opportunity;
use Modules\Crm\Domain\Repositories\OpportunityRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class DatabaseOpportunityRepository implements OpportunityRepositoryInterface
{
    public function save(Opportunity $opportunity): void
    {
        $row = $opportunity->toRow();
        $exists = DB::table('crm_opportunities')->where('id', $opportunity->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('crm_opportunities')->where('id', $opportunity->id())->update($row);
        } else {
            DB::table('crm_opportunities')->insert($row);
        }
    }

    public function findById(string $id): ?Opportunity
    {
        $row = DB::table('crm_opportunities')->where('id', $id)->first();

        return $row === null ? null : Opportunity::fromRow($row);
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('crm_opportunities');
        $rows = DB::table('crm_opportunities');

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['stage']) && (string) $filters['stage'] !== '') {
                $q->where('stage', (string) $filters['stage']);
            }
            if (isset($filters['owner_id']) && $filters['owner_id'] !== null) {
                $q->where('owner_id', (int) $filters['owner_id']);
            }
            if (isset($filters['contact_id']) && (string) $filters['contact_id'] !== '') {
                $q->where('contact_id', (string) $filters['contact_id']);
            }
            if (isset($filters['search']) && (string) $filters['search'] !== '') {
                $q->where('title', 'LIKE', '%' . (string) $filters['search'] . '%');
            }
        };
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('updated_at')->orderByDesc('created_at')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): Opportunity => Opportunity::fromRow($row), $data),
            'total' => $total,
        ];
    }

    public function forContact(string $contactId): array
    {
        $rows = DB::table('crm_opportunities')->where('contact_id', $contactId)->orderByDesc('created_at')->get();

        return \array_map(static fn (array $row): Opportunity => Opportunity::fromRow($row), $rows);
    }

    public function openOpportunities(int $limit = 5000): array
    {
        $rows = DB::table('crm_opportunities')
            ->whereNotIn('stage', ['closed_won', 'closed_lost'])
            ->orderBy('stage')
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(static fn (array $row): Opportunity => Opportunity::fromRow($row), $rows);
    }

    public function countByStage(): array
    {
        $rows = DB::table('crm_opportunities')->get();
        $counts = [];
        foreach ($rows as $row) {
            $stage = (string) ($row['stage'] ?? '');
            $counts[$stage] = ($counts[$stage] ?? 0) + 1;
        }

        return $counts;
    }

    public function closedBetween(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array
    {
        $query = DB::table('crm_opportunities')->whereIn('stage', ['closed_won', 'closed_lost']);
        if ($from !== null) {
            $query->where('closed_at', '>=', $from->format('Y-m-d H:i:s.v'));
        }
        if ($to !== null) {
            $query->where('closed_at', '<=', $to->format('Y-m-d H:i:s.v'));
        }

        return \array_map(static fn (array $row): Opportunity => Opportunity::fromRow($row), $query->get());
    }

    public function wonBetween(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array
    {
        $query = DB::table('crm_opportunities')->where('stage', 'closed_won');
        if ($from !== null) {
            $query->where('closed_at', '>=', $from->format('Y-m-d H:i:s.v'));
        }
        if ($to !== null) {
            $query->where('closed_at', '<=', $to->format('Y-m-d H:i:s.v'));
        }

        return \array_map(static fn (array $row): Opportunity => Opportunity::fromRow($row), $query->get());
    }
}
