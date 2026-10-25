<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * CrmReportService — گزارش‌های DOC-096 §6.
 */

namespace Modules\Crm\Application\UseCases;

use Modules\Crm\Domain\Repositories\LeadRepositoryInterface;
use Modules\Crm\Domain\Repositories\OpportunityRepositoryInterface;
use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Tavaan\Support\Time\Clock;

/**
 * CrmReportService — هفت گزارش مستند (§6):
 *
 * 1. Pipeline value by stage   2. Win/Loss rate   3. Sales forecast (weighted)
 * 4. Agent performance         5. Ticket resolution/SLA compliance
 * 6. Lead source effectiveness 7. Revenue by period
 *
 * خروجی: آرایه (پنل HTML) + CSV (BOM + ضد CSV Injection — الگوی report/LMS).
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class CrmReportService
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunities,
        private readonly LeadRepositoryInterface $leads,
        private readonly TicketRepositoryInterface $tickets,
        private readonly PipelineService $pipeline,
    ) {
    }

    /** گزارش کامل یک‌جا (پنل). @return array<string, mixed> */
    public function overview(): array
    {
        return [
            'pipeline' => $this->pipeline->forecast(),
            'win_loss' => $this->winLoss(),
            'lead_sources' => $this->leads->statsBySource(),
            'tickets' => $this->tickets->stats(),
            'agents' => $this->agentPerformance(),
            'revenue' => $this->revenueByPeriod(),
        ];
    }

    /** §6-2: Win/Loss rate. */
    public function winLoss(): array
    {
        $counts = $this->opportunities->countByStage();

        $won = (int) ($counts['closed_won'] ?? 0);
        $lost = (int) ($counts['closed_lost'] ?? 0);
        $open = 0;
        $openValue = 0;
        foreach ($this->opportunities->openOpportunities() as $opp) {
            $open++;
            $openValue += $opp->valueMinor();
        }

        $closed = $won + $lost;

        return [
            'won' => $won,
            'lost' => $lost,
            'open' => $open,
            'open_value' => $openValue,
            'win_rate' => $closed > 0 ? \round($won * 100 / $closed, 1) : 0.0,
        ];
    }

    /** §6-4: Agent performance (فروش + پشتیبانی). @return list<array<string, mixed>> */
    public function agentPerformance(): array
    {
        $rows = [];

        // فروش — برنده‌ها/ارزش به تفکیک owner
        foreach ($this->opportunities->wonBetween(null, null) as $won) {
            $owner = $won->ownerId() ?? 0;
            $rows[$owner] ??= ['owner_id' => $owner, 'won_count' => 0, 'won_value' => 0];
            $rows[$owner]['won_count']++;
            $rows[$owner]['won_value'] += $won->valueMinor();
        }

        // پشتیبانی — ادغام آمار تیکت
        foreach ($this->tickets->agentStats() as $agentId => $stat) {
            $rows[$agentId] ??= ['owner_id' => $agentId, 'won_count' => 0, 'won_value' => 0];
            $rows[$agentId]['tickets_assigned'] = (int) $stat['assigned'];
            $rows[$agentId]['tickets_resolved'] = (int) $stat['resolved'];
        }

        $out = [];
        foreach ($rows as $row) {
            $row['tickets_assigned'] ??= 0;
            $row['tickets_resolved'] ??= 0;
            $out[] = $row;
        }

        \usort($out, static fn (array $a, array $b): int => ($b['won_value'] + $b['tickets_resolved']) <=> ($a['won_value'] + $a['tickets_resolved']));

        return $out;
    }

    /** §6-7: Revenue by period — won به تفکیک ماه (۱۲ ماه اخیر). @return array<string, int> */
    public function revenueByPeriod(int $months = 12): array
    {
        $now = Clock::utcNow();
        $from = $now->sub(new \DateInterval('P' . \max(1, $months) . 'M'));

        $buckets = [];
        $cursor = $from->setDate((int) $from->format('Y'), (int) $from->format('m'), 1);
        while ($cursor <= $now) {
            $buckets[$cursor->format('Y-m')] = 0;
            $cursor = $cursor->add(new \DateInterval('P1M'));
        }

        foreach ($this->opportunities->wonBetween($from, $now) as $won) {
            $closedAt = $won->closedAt();
            if ($closedAt === null) {
                continue;
            }
            $key = $closedAt->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key] += $won->valueMinor();
            }
        }

        return $buckets;
    }

    /** CSV گزارش pipeline (دانلود پنل). @param array<string, mixed> $overview */
    public function toCsv(array $overview): string
    {
        $lines = ['section,key,count,value_minor,weighted_minor,extra'];

        foreach ((array) ($overview['pipeline']['by_stage'] ?? []) as $stage => $row) {
            $lines[] = \implode(',', [
                'pipeline', self::csvSafe((string) $stage), (string) (int) $row['count'],
                (string) (int) $row['value'], (string) (int) $row['weighted'], '',
            ]);
        }

        $wl = (array) ($overview['win_loss'] ?? []);
        $lines[] = \implode(',', ['win_loss', 'won', (string) (int) ($wl['won'] ?? 0), '', '', '']);
        $lines[] = \implode(',', ['win_loss', 'lost', (string) (int) ($wl['lost'] ?? 0), '', '', '']);
        $lines[] = \implode(',', ['win_loss', 'win_rate', '', '', '', (string) (float) ($wl['win_rate'] ?? 0) . '%']);
        $lines[] = \implode(',', ['forecast', 'weighted_total', (string) (int) ($overview['pipeline']['count'] ?? 0), (string) (int) ($overview['pipeline']['total_value'] ?? 0), (string) (int) ($overview['pipeline']['weighted'] ?? 0), '']);

        foreach ((array) ($overview['lead_sources'] ?? []) as $source => $stat) {
            $lines[] = \implode(',', [
                'lead_source', self::csvSafe((string) $source), (string) (int) $stat['total'],
                '', '', 'converted=' . (string) (int) $stat['converted'],
            ]);
        }

        $tickets = (array) ($overview['tickets'] ?? []);
        $lines[] = \implode(',', [
            'tickets', 'total', (string) (int) ($tickets['total'] ?? 0), '', '',
            'open=' . (string) (int) ($tickets['open'] ?? 0) . ';sla=' . (string) (float) ($tickets['sla_compliance'] ?? 0) . '%',
        ]);

        return "\xEF\xBB\xBF" . \implode("\n", $lines);
    }

    /** 🔒 ضد CSV Injection — سلول‌های خطرناک با ' prefix. */
    private static function csvSafe(string $value): string
    {
        if ($value !== '' && \str_contains("=+-@\t\r", $value[0])) {
            $value = "'" . $value;
        }

        return '"' . \str_replace('"', '""', $value) . '"';
    }
}
