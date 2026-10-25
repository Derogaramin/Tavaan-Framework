<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * DatabaseLeadRepository — آداپتر سرنخ (crm_leads).
 */

namespace Modules\Crm\Infrastructure\Persistence;

use Modules\Crm\Domain\Entities\Lead;
use Modules\Crm\Domain\Repositories\LeadRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class DatabaseLeadRepository implements LeadRepositoryInterface
{
    public function save(Lead $lead): void
    {
        $row = $lead->toRow();
        $exists = DB::table('crm_leads')->where('id', $lead->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('crm_leads')->where('id', $lead->id())->update($row);
        } else {
            DB::table('crm_leads')->insert($row);
        }
    }

    public function findById(string $id): ?Lead
    {
        $row = DB::table('crm_leads')->where('id', $id)->first();

        return $row === null ? null : Lead::fromRow($row, $this->contactSourceOf((string) ($row['contact_id'] ?? '')));
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('crm_leads');
        $rows = DB::table('crm_leads');

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['status']) && (string) $filters['status'] !== '') {
                $q->where('status', (string) $filters['status']);
            }
            if (isset($filters['owner_id']) && $filters['owner_id'] !== null) {
                $q->where('owner_id', (int) $filters['owner_id']);
            }
            if (isset($filters['source']) && (string) $filters['source'] !== '') {
                $q->where('source', (string) $filters['source']);
            }
            if (isset($filters['min_score']) && $filters['min_score'] !== null) {
                $q->where('score', '>=', (int) $filters['min_score']);
            }
            if (isset($filters['search']) && (string) $filters['search'] !== '') {
                $q->where('title', 'LIKE', '%' . (string) $filters['search'] . '%');
            }
        };
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): Lead => Lead::fromRow($row), $data),
            'total' => $total,
        ];
    }

    public function forContact(string $contactId): array
    {
        $rows = DB::table('crm_leads')->where('contact_id', $contactId)->orderByDesc('created_at')->get();

        return \array_map(static fn (array $row): Lead => Lead::fromRow($row), $rows);
    }

    public function all(int $limit = 1000): array
    {
        $rows = DB::table('crm_leads')->orderByDesc('created_at')->limit(\max(1, $limit))->get();

        return \array_map(static fn (array $row): Lead => Lead::fromRow($row), $rows);
    }

    public function statsBySource(): array
    {
        $rows = DB::table('crm_leads')->get();
        $stats = [];
        foreach ($rows as $row) {
            $source = (string) ($row['source'] ?? 'website');
            $stats[$source] ??= ['total' => 0, 'qualified' => 0, 'converted' => 0, 'lost' => 0];
            $stats[$source]['total']++;
            $status = (string) ($row['status'] ?? 'new');
            if ($status === 'qualified') {
                $stats[$source]['qualified']++;
            } elseif ($status === 'converted') {
                $stats[$source]['converted']++;
            } elseif ($status === 'lost') {
                $stats[$source]['lost']++;
            }
        }

        return $stats;
    }

    private function contactSourceOf(string $contactId): string
    {
        if ($contactId === '') {
            return 'website';
        }

        $row = DB::table('crm_contacts')->where('id', $contactId)->first();

        return (string) ($row['source'] ?? 'website');
    }
}
