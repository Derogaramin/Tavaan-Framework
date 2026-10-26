<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * MonitoringService — داشبورد پایش فرایندها (DOC-098 §8).
 */

namespace Modules\Bpms\Application\UseCases;

use Modules\Bpms\Domain\Repositories\HistoryRepositoryInterface;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Modules\Bpms\Domain\Services\MonitoringCalculator;
use Tavaan\Support\Time\Clock;

/**
 * MonitoringService — KPIهای پایش:
 * - نمونه‌های فعال/تکمیل‌شده/معلق/متوقف + میانگین چرخه
 * - زمان متوسط هر state و bottleneckها
 * - SLA compliance (کارهای به‌موقع/کل)
 * - بار کاری هر assignee
 *
 * محاسبات در MonitoringCalculator (دامنهٔ خالص) — این سرویس فقط
 * ردیف‌های خام را از مخزن‌ها می‌گیرد.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class MonitoringService
{
    /** @param array<string, mixed> $config کلید config('bpms.monitoring') */
    public function __construct(
        private readonly InstanceRepositoryInterface $instances,
        private readonly TaskRepositoryInterface $tasks,
        private readonly HistoryRepositoryInterface $history,
        private readonly array $config = [],
    ) {
    }

    /**
     * خلاصهٔ کامل داشبورد.
     *
     * @return array<string, mixed>
     */
    public function dashboard(?string $definitionKey = null): array
    {
        $instanceRows = $this->instances->rawForMonitoring($definitionKey);
        $taskRows = $this->tasks->rawForMonitoring($definitionKey);
        $historyRows = $this->history->rawForMonitoring($definitionKey);

        $top = (int) ($this->config['bottleneck_top'] ?? 5);

        return [
            'instances' => MonitoringCalculator::instanceCounts($instanceRows),
            'avg_cycle_ms' => MonitoringCalculator::averageCycleTimeMs($instanceRows),
            'bottlenecks' => MonitoringCalculator::bottlenecks($historyRows, $top),
            'state_durations' => MonitoringCalculator::stateDurations($historyRows),
            'sla' => MonitoringCalculator::slaCompliance($taskRows),
            'workload' => MonitoringCalculator::workload($taskRows, Clock::now()),
            'recent' => $this->recentActivity($historyRows, (int) ($this->config['recent_activity'] ?? 12)),
        ];
    }

    /**
     * ردیف‌های CSV گزارش پایش (خروجی دانلود — الگوی CRM/ERP).
     *
     * @return list<array<string, int|float|string|null>>
     */
    public function csvRows(?string $definitionKey = null): array
    {
        $data = $this->dashboard($definitionKey);

        $rows = [];
        $rows[] = ['section' => 'instances', 'metric' => 'running', 'value' => $data['instances']['running'] ?? 0];
        $rows[] = ['section' => 'instances', 'metric' => 'completed', 'value' => $data['instances']['completed'] ?? 0];
        $rows[] = ['section' => 'instances', 'metric' => 'suspended', 'value' => $data['instances']['suspended'] ?? 0];
        $rows[] = ['section' => 'instances', 'metric' => 'terminated', 'value' => $data['instances']['terminated'] ?? 0];
        $rows[] = ['section' => 'instances', 'metric' => 'avg_cycle_ms', 'value' => $data['avg_cycle_ms']];

        foreach ($data['bottlenecks'] as $bottleneck) {
            $rows[] = [
                'section' => 'bottleneck',
                'metric' => (string) $bottleneck['state'],
                'value' => $bottleneck['avg_ms'],
            ];
        }

        $sla = $data['sla'];
        $rows[] = ['section' => 'sla', 'metric' => 'completed', 'value' => $sla['completed']];
        $rows[] = ['section' => 'sla', 'metric' => 'with_due', 'value' => $sla['with_due']];
        $rows[] = ['section' => 'sla', 'metric' => 'on_time', 'value' => $sla['on_time']];
        $rows[] = ['section' => 'sla', 'metric' => 'compliance_pct', 'value' => $sla['compliance']];

        foreach ($data['workload'] as $assignee => $load) {
            $rows[] = [
                'section' => 'workload',
                'metric' => (string) $assignee,
                'value' => $load['open'],
            ];
        }

        return $rows;
    }

    /**
     * خروجی CSV (BOM برای Excel + ضد injection — الگوی CRM/ERP).
     *
     * @param list<array<string, int|float|string|null>> $rows
     */
    public function toCsv(array $rows): string
    {
        $out = "\xEF\xBB\xBF";   // BOM — نمایش درست UTF-8 در Excel
        $out .= "section,metric,value\n";

        foreach ($rows as $row) {
            $cells = [];
            foreach (['section', 'metric', 'value'] as $key) {
                $cells[] = $this->csvCell($row[$key] ?? '');
            }
            $out .= \implode(',', $cells) . "\n";
        }

        return $out;
    }

    /**
     * فعالیت اخیر (timeline سراسری).
     *
     * @param list<array<string, mixed>> $historyRows
     *
     * @return list<array<string, mixed>>
     */
    private function recentActivity(array $historyRows, int $limit): array
    {
        $rows = $historyRows;
        \usort($rows, static fn (array $a, array $b): int => \strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? '')));

        return \array_slice($rows, 0, $limit);
    }

    /** 🔒 سلول CSV با خنثی‌سازی formula injection. */
    private function csvCell(mixed $value): string
    {
        $text = \is_scalar($value) ? (string) $value : '';
        if ($text !== '' && \str_contains("=+-@\t\r", $text[0])) {
            $text = "'" . $text;
        }
        if (\str_contains($text, ',') || \str_contains($text, '"') || \str_contains($text, "\n")) {
            $text = '"' . \str_replace('"', '""', $text) . '"';
        }

        return $text;
    }
}
