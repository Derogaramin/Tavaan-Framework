<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * MonitoringCalculator — محاسبهٔ خالص KPIهای پایش (DOC-098 §8).
 */

namespace Modules\Bpms\Domain\Services;

/**
 * MonitoringCalculator — ورودی: ردیف‌های خام (history/tasks/instances) →
 * خروجی: KPI (میانگین زمان، bottleneck، SLA compliance، بار کاری).
 *
 * دامنهٔ خالص و قطعی (تست‌پذیر بدون DB). زمان‌ها با DateTimeImmutable پارس
 * می‌شوند؛ duration_ms در history منبع نخست و created→completed در task
 * منبع جایگزین است.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class MonitoringCalculator
{
    private function __construct()
    {
    }

    /**
     * میانگین مدت (ms) هر state از ردیف‌های history (to_state محور با duration_ms).
     *
     * @param list<array<string, mixed>> $historyRows
     *
     * @return array<string, array{avg_ms: int, count: int, max_ms: int}>
     */
    public static function stateDurations(array $historyRows): array
    {
        $sums = [];
        foreach ($historyRows as $row) {
            $state = (string) ($row['from_state'] ?? '');
            $duration = $row['duration_ms'] ?? null;
            if ($state === '' || $duration === null) {
                continue;
            }
            $duration = (int) $duration;
            $sums[$state] ??= ['sum' => 0, 'count' => 0, 'max' => 0];
            $sums[$state]['sum'] += $duration;
            $sums[$state]['count']++;
            $sums[$state]['max'] = \max($sums[$state]['max'], $duration);
        }

        $result = [];
        foreach ($sums as $state => $agg) {
            $result[$state] = [
                'avg_ms' => (int) \intdiv($agg['sum'], $agg['count']),
                'count' => $agg['count'],
                'max_ms' => $agg['max'],
            ];
        }
        \uasort($result, static fn (array $a, array $b): int => $b['avg_ms'] <=> $a['avg_ms']);

        return $result;
    }

    /**
     * bottleneck — N state کندترین (میانگین).
     *
     * @param list<array<string, mixed>> $historyRows
     *
     * @return list<array{state: string, avg_ms: int, count: int}>
     */
    public static function bottlenecks(array $historyRows, int $top = 5): array
    {
        $durations = self::stateDurations($historyRows);
        $result = [];
        foreach ($durations as $state => $stat) {
            $result[] = ['state' => (string) $state, 'avg_ms' => $stat['avg_ms'], 'count' => $stat['count']];
            if (\count($result) >= $top) {
                break;
            }
        }

        return $result;
    }

    /**
     * SLA compliance — درصد کارهای تکمیل‌شدهٔ به‌موقع (completed_at <= due_at).
     * کارهای بدون due در شمار نمی‌آیند.
     *
     * @param list<array<string, mixed>> $taskRows
     *
     * @return array{completed: int, with_due: int, on_time: int, compliance: float|null}
     */
    public static function slaCompliance(array $taskRows): array
    {
        $completed = 0;
        $withDue = 0;
        $onTime = 0;

        foreach ($taskRows as $row) {
            if ((string) ($row['status'] ?? '') !== 'completed') {
                continue;
            }
            $completed++;
            $due = $row['due_at'] ?? null;
            $done = $row['completed_at'] ?? null;
            if ($due === null || $done === null) {
                continue;
            }
            try {
                $dueAt = new \DateTimeImmutable((string) $due);
                $doneAt = new \DateTimeImmutable((string) $done);
            } catch (\Exception) {
                continue;
            }
            $withDue++;
            if ($doneAt <= $dueAt) {
                $onTime++;
            }
        }

        return [
            'completed' => $completed,
            'with_due' => $withDue,
            'on_time' => $onTime,
            'compliance' => $withDue === 0 ? null : \round($onTime / $withDue * 100, 1),
        ];
    }

    /**
     * بار کاری assigneeها — کارهای باز (pending/claimed) به تفکیک assignee.
     *
     * @param list<array<string, mixed>> $taskRows
     *
     * @return array<string, array{open: int, overdue: int, escalated: int}>
     */
    public static function workload(array $taskRows, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now');
        $workload = [];

        foreach ($taskRows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status !== 'pending' && $status !== 'claimed') {
                continue;
            }
            $type = (string) ($row['assignee_type'] ?? 'anyone');
            $id = (string) ($row['assignee_id'] ?? '');
            $key = $type . ($id !== '' ? ':' . $id : '');

            $workload[$key] ??= ['open' => 0, 'overdue' => 0, 'escalated' => 0];
            $workload[$key]['open']++;

            $due = $row['due_at'] ?? null;
            if ($due !== null) {
                try {
                    if (new \DateTimeImmutable((string) $due) <= $now) {
                        $workload[$key]['overdue']++;
                    }
                } catch (\Exception) {
                    // بی‌اثر
                }
            }
            if (($row['escalated_at'] ?? null) !== null) {
                $workload[$key]['escalated']++;
            }
        }

        \uasort($workload, static fn (array $a, array $b): int => $b['open'] <=> $a['open']);

        return $workload;
    }

    /**
     * شمارش نمونه‌ها per وضعیت.
     *
     * @param list<array<string, mixed>> $instanceRows
     *
     * @return array<string, int>
     */
    public static function instanceCounts(array $instanceRows): array
    {
        $counts = ['running' => 0, 'completed' => 0, 'suspended' => 0, 'terminated' => 0];
        foreach ($instanceRows as $row) {
            $status = (string) ($row['status'] ?? 'running');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    /** میانگین زمان تکمیل نمونه‌های completed (ms) از started_at/completed_at. */
    public static function averageCycleTimeMs(array $instanceRows): ?int
    {
        $sum = 0;
        $count = 0;
        foreach ($instanceRows as $row) {
            if ((string) ($row['status'] ?? '') !== 'completed') {
                continue;
            }
            $started = $row['started_at'] ?? null;
            $completed = $row['completed_at'] ?? null;
            if ($started === null || $completed === null) {
                continue;
            }
            try {
                $from = new \DateTimeImmutable((string) $started);
                $to = new \DateTimeImmutable((string) $completed);
            } catch (\Exception) {
                continue;
            }
            $ms = ($to->getTimestamp() - $from->getTimestamp()) * 1000
                + ((int) $to->format('v') - (int) $from->format('v'));
            if ($ms >= 0) {
                $sum += $ms;
                $count++;
            }
        }

        return $count === 0 ? null : (int) \intdiv($sum, $count);
    }

    /** قالب‌بندی مدت (ms) → رشتهٔ خوانا (ویو/CSV). */
    public static function formatDuration(?int $ms): string
    {
        if ($ms === null) {
            return '—';
        }
        $seconds = \intdiv($ms, 1000);
        $days = \intdiv($seconds, 86400);
        $hours = \intdiv($seconds % 86400, 3600);
        $minutes = \intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $days . 'd ' . $hours . 'h';
        }
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        if ($minutes > 0) {
            return $minutes . 'm ' . ($seconds % 60) . 's';
        }
        if ($seconds > 0) {
            return $seconds . 's';
        }

        return $ms . 'ms';
    }
}
