<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * DatabaseTicketRepository — آداپتر تیکت + پاسخ‌ها (crm_tickets/crm_ticket_replies).
 */

namespace Modules\Crm\Infrastructure\Persistence;

use Modules\Crm\Domain\Entities\Ticket;
use Modules\Crm\Domain\Entities\TicketReply;
use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class DatabaseTicketRepository implements TicketRepositoryInterface
{
    public function save(Ticket $ticket): void
    {
        $row = $ticket->toRow();
        $exists = DB::table('crm_tickets')->where('id', $ticket->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at'], $row['ticket_number']);
            DB::table('crm_tickets')->where('id', $ticket->id())->update($row);
        } else {
            DB::table('crm_tickets')->insert($row);
        }
    }

    public function findById(string $id): ?Ticket
    {
        $row = DB::table('crm_tickets')->where('id', $id)->first();

        return $row === null ? null : Ticket::fromRow($row);
    }

    public function findByNumber(string $ticketNumber): ?Ticket
    {
        $row = DB::table('crm_tickets')->where('ticket_number', $ticketNumber)->first();

        return $row === null ? null : Ticket::fromRow($row);
    }

    public function numberExists(string $ticketNumber): bool
    {
        return DB::table('crm_tickets')->where('ticket_number', $ticketNumber)->exists();
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('crm_tickets');
        $rows = DB::table('crm_tickets');

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['status']) && (string) $filters['status'] !== '') {
                $q->where('status', (string) $filters['status']);
            }
            if (isset($filters['priority']) && (string) $filters['priority'] !== '') {
                $q->where('priority', (string) $filters['priority']);
            }
            if (isset($filters['assigned_to']) && $filters['assigned_to'] !== null) {
                $q->where('assigned_to', (int) $filters['assigned_to']);
            }
            if (isset($filters['contact_id']) && (string) $filters['contact_id'] !== '') {
                $q->where('contact_id', (string) $filters['contact_id']);
            }
            if (($filters['escalated'] ?? null) === true) {
                $q->whereNotNull('escalated_at');
            } elseif (($filters['escalated'] ?? null) === false) {
                $q->whereNull('escalated_at');
            }
            if (isset($filters['mine']) && $filters['mine'] !== null) {
                $q->where('assigned_to', (int) $filters['mine']);
            }
            if (isset($filters['search']) && (string) $filters['search'] !== '') {
                $term = '%' . (string) $filters['search'] . '%';
                $q->where(static function (mixed $w) use ($term): void {
                    $w->where('subject', 'LIKE', $term)->orWhere('ticket_number', 'LIKE', $term);
                });
            }
        };
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): Ticket => Ticket::fromRow($row), $data),
            'total' => $total,
        ];
    }

    public function openOverdue(\DateTimeImmutable $now, int $limit = 500): array
    {
        $rows = DB::table('crm_tickets')
            ->where('status', 'open')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', $now->format('Y-m-d H:i:s.v'))
            ->whereNull('escalated_at')
            ->orderBy('sla_due_at')
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(static fn (array $row): Ticket => Ticket::fromRow($row), $rows);
    }

    public function resolvedOlderThan(\DateTimeImmutable $cutoff, int $limit = 200): array
    {
        $rows = DB::table('crm_tickets')
            ->where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<', $cutoff->format('Y-m-d H:i:s.v'))
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(static fn (array $row): Ticket => Ticket::fromRow($row), $rows);
    }

    public function forContact(string $contactId): array
    {
        $rows = DB::table('crm_tickets')->where('contact_id', $contactId)->orderByDesc('created_at')->get();

        return \array_map(static fn (array $row): Ticket => Ticket::fromRow($row), $rows);
    }

    // ── پاسخ‌ها ───────────────────────────────────────────────────────────

    public function saveReply(TicketReply $reply): void
    {
        $row = $reply->toRow();
        $exists = DB::table('crm_ticket_replies')->where('id', $reply->id())->exists();

        if ($exists) {
            unset($row['id']);
            DB::table('crm_ticket_replies')->where('id', $reply->id())->update($row);
        } else {
            DB::table('crm_ticket_replies')->insert($row);
        }
    }

    public function repliesOf(string $ticketId, bool $includeInternal = true): array
    {
        $query = DB::table('crm_ticket_replies')->where('ticket_id', $ticketId);
        if (!$includeInternal) {
            $query->where('is_internal', false);
        }

        $rows = $query->orderBy('created_at')->orderBy('id')->get();

        return \array_map(static fn (array $row): TicketReply => TicketReply::fromRow($row), $rows);
    }

    // ── آمار (DOC-096 §6: ticket resolution time / SLA compliance) ────────

    public function stats(): array
    {
        $rows = DB::table('crm_tickets')->get();

        $total = \count($rows);
        $byStatus = ['open' => 0, 'pending' => 0, 'resolved' => 0, 'closed' => 0];
        $escalated = 0;
        $resolutionSum = 0;
        $resolutionCount = 0;
        $slaChecked = 0;
        $slaMet = 0;

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'open');
            if (isset($byStatus[$status])) {
                $byStatus[$status]++;
            }
            if (($row['escalated_at'] ?? null) !== null) {
                $escalated++;
            }

            $created = self::date($row['created_at'] ?? null);
            $resolved = self::date($row['resolved_at'] ?? null);
            if ($created !== null && $resolved !== null) {
                $resolutionSum += \max(0, $resolved->getTimestamp() - $created->getTimestamp());
                $resolutionCount++;

                // SLA compliance: تا لحظهٔ resolve، escalate نشده = رعایت‌شده
                $slaChecked++;
                if (($row['escalated_at'] ?? null) === null) {
                    $slaMet++;
                }
            }
        }

        return [
            'total' => $total,
            'open' => $byStatus['open'],
            'pending' => $byStatus['pending'],
            'resolved' => $byStatus['resolved'],
            'closed' => $byStatus['closed'],
            'escalated' => $escalated,
            'avg_resolution_minutes' => $resolutionCount > 0 ? \round($resolutionSum / $resolutionCount / 60, 1) : 0.0,
            'sla_compliance' => $slaChecked > 0 ? \round($slaMet * 100 / $slaChecked, 1) : 100.0,
        ];
    }

    public function agentStats(): array
    {
        $rows = DB::table('crm_tickets')->whereNotNull('assigned_to')->get();

        $stats = [];
        foreach ($rows as $row) {
            $agent = (int) $row['assigned_to'];
            $stats[$agent] ??= ['assigned' => 0, 'resolved' => 0];
            $stats[$agent]['assigned']++;
            if (\in_array((string) ($row['status'] ?? ''), ['resolved', 'closed'], true)) {
                $stats[$agent]['resolved']++;
            }
        }

        return $stats;
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
