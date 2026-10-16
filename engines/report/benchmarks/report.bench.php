<?php

/**
 * تَوان | Tavaan — Report Benchmark (DOC-072 §9)
 *
 * اهداف:
 * - Report ۱۰۰۰۰ ردیف (html)        ≤ ۲s
 * - CSV streaming ۱۰۰۰۰۰ ردیف        سربار حافظه ≤ ۵MB
 * - xlsx ۱۰۰۰۰ ردیف                  ≤ ۳s
 * - Pivot aggregation                ≤ ۱s
 *
 * اجرا: php engines/report/benchmarks/report.bench.php
 */

declare(strict_types=1);

require \dirname(__DIR__, 3) . '/core/bootstrap.php';

use Tavaan\Engine\Report\Export\HtmlExporter;
use Tavaan\Engine\Report\ReportManager;

$config = [
    'max_rows' => 200_000,
    'streaming_threshold' => 1000,
    'csv' => ['delimiter' => ';', 'bom' => true, 'enclosure' => '"'],
    'xlsx' => ['driver' => 'pure'],
];

$manager = new ReportManager($config);

/** @return list<array<string, mixed>> */
function makeRows(int $count): array
{
    $rows = [];
    $statuses = ['paid', 'pending', 'canceled'];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = [
            'created_at' => '2026-09-' . \str_pad((string) (1 + $i % 28), 2, '0', STR_PAD_LEFT) . ' 10:00:00',
            'total' => 100_000 + $i * 37,
            'status' => $statuses[$i % 3],
            'city' => 'شهر ' . ($i % 50),
        ];
    }

    return $rows;
}

function buildReport(ReportManager $manager, array $rows): \Tavaan\Engine\Report\ReportBuilder
{
    return $manager->build('bench-sales')
        ->dataset($rows)
        ->addField('created_at', label: 'تاریخ', type: 'date', format: 'jalali')
        ->addField('total', label: 'مبلغ', type: 'money', aggregate: 'sum')
        ->addField('status', label: 'وضعیت')
        ->addField('city', label: 'شهر');
}

// ── HTML ۱۰٬۰۰۰ ردیف (هدف ≤ ۲s) ──────────────────────────────────────────
$rows10k = makeRows(10_000);
$start = \hrtime(true);
$result = buildReport($manager, $rows10k)->run();
$html = (new HtmlExporter())->export($result);
$htmlMs = (\hrtime(true) - $start) / 1e6;
\printf("html 10k rows                    %10.1f ms (target ≤ 2000 ms) %s\n", $htmlMs, $htmlMs <= 2000 ? '✅' : '❌');

// ── xlsx ۱۰٬۰۰۰ ردیف (هدف ≤ ۳s) ──────────────────────────────────────────
$start = \hrtime(true);
$xlsx = $manager->export($result, 'xlsx');
$xlsxMs = (\hrtime(true) - $start) / 1e6;
\printf("xlsx 10k rows                    %10.1f ms (target ≤ 3000 ms) %s  [%s KB]\n",
    $xlsxMs, $xlsxMs <= 3000 ? '✅' : '❌', \number_format(\intdiv(\strlen($xlsx), 1024)));

// ── CSV ۱۰٬۰۰۰ ردیف ──────────────────────────────────────────────────────
$start = \hrtime(true);
$csv = $manager->export($result, 'csv');
$csvMs = (\hrtime(true) - $start) / 1e6;
\printf("csv 10k rows                     %10.1f ms\n", $csvMs);

// ── Pivot aggregation (هدف ≤ ۱s) ─────────────────────────────────────────
$start = \hrtime(true);
$pivotResult = buildReport($manager, $rows10k)
    ->pivot(rows: 'status', columns: 'city', values: 'total', aggregate: 'sum')
    ->run();
$pivotMs = (\hrtime(true) - $start) / 1e6;
\printf("pivot 10k rows (3×50)            %10.1f ms (target ≤ 1000 ms) %s\n", $pivotMs, $pivotMs <= 1000 ? '✅' : '❌');

// ── CSV streaming ۱۰۰٬۰۰۰ ردیف — سربار حافظه (هدف ≤ ۵MB) ────────────────
$rows100k = makeRows(100_000);
$bigResult = buildReport($manager, $rows100k)->run();

$handle = \fopen('php://temp/maxmemory:2097152', 'wb');
\gc_collect_cycles();
$memBefore = \memory_get_usage();
$start = \hrtime(true);
$manager->stream($bigResult, 'csv', $handle);
$streamMs = (\hrtime(true) - $start) / 1e6;
$memDelta = \max(0, \memory_get_usage() - $memBefore);
\fclose($handle);

\printf("csv streaming 100k rows          %10.1f ms — overhead %.2f MB (target ≤ 5 MB) %s\n",
    $streamMs, $memDelta / 1048576, $memDelta <= 5 * 1048576 ? '✅' : '❌');

\printf("peak memory                      %10.1f MB\n", \memory_get_peak_usage(true) / 1048576);
