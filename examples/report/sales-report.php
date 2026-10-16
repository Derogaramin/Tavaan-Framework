<?php

/**
 * تَوان | Tavaan — نمونهٔ گزارش‌ساز: فروش (M7 / DOC-072)
 *
 * اجرا: php examples/report/sales-report.php
 *
 * نمایش: dataset + فیلتر + groupBy/subtotal + pivot → خروجی CSV (با BOM)
 * و xlsx خودنوشته در storage/temp.
 */

declare(strict_types=1);

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Engine\Report\ReportManager;

require \dirname(__DIR__, 2) . '/core/bootstrap.php';

$app = ApplicationBuilder::create(TVN_BASE_PATH)->forConsole()->boot();

/** @var ReportManager $reports */
$reports = $app->make('report');

// ── ۱) دادهٔ نمونه (در عمل: Query Builder روی جداول واقعی) ────────────────
$rows = [];
$statuses = ['paid', 'pending', 'canceled'];
$cities = ['تهران', 'مشهد', 'شیراز', 'تبریز', 'اصفهان'];
for ($i = 0; $i < 240; $i++) {
    $rows[] = [
        'created_at' => \sprintf('2026-%02d-%02d 10:00:00', 8 + \intdiv($i, 60), 1 + ($i % 28)),
        'total' => 500_000 + ($i * 37_000),
        'status' => $statuses[$i % 3],
        'city' => $cities[$i % 5],
    ];
}

// ── ۲) ساخت گزارش ─────────────────────────────────────────────────────────
$report = $reports->build('sales')
    ->dataset($rows)
    ->addField('created_at', label: 'تاریخ', type: 'date', format: 'jalali')
    ->addField('total', label: 'مبلغ', type: 'money', aggregate: 'sum')
    ->addField('status', label: 'وضعیت')
    ->addField('city', label: 'شهر')
    ->addFilter('date_range', field: 'created_at', type: 'daterange')
    ->addFilter('status', field: 'status', type: 'select', options: ['paid', 'pending', 'canceled'])
    ->groupBy('status', subtotal: ['total' => 'sum'])
    ->pivot(rows: 'status', columns: 'city', values: 'total', aggregate: 'sum')
    ->orderBy('total', 'desc');

$result = $report->run(['status' => 'paid']);

echo 'ردیف‌ها: ' . $result->count() . PHP_EOL;
echo 'جمع کل: ' . \number_format((float) $result->totals()['total']) . ' تومان' . PHP_EOL;
echo 'جمع جزء per وضعیت: ' . \json_encode($result->subtotals(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo 'ستون‌های pivot: ' . \implode(', ', $result->pivot()['columns']) . PHP_EOL;

// ── ۳) خروجی‌ها ───────────────────────────────────────────────────────────
$csv = $reports->export($report, 'csv', filters: ['status' => 'paid']);
echo PHP_EOL . 'CSV (۳ خط اول — با BOM و delimiter «;»):' . PHP_EOL;
foreach (\array_slice(\explode("\n", \substr($csv, 3)), 0, 3) as $line) {
    echo '  ' . $line . PHP_EOL;
}

$xlsxPath = \storage_path('temp/sales-example.xlsx');
$reports->export($report, 'xlsx', path: $xlsxPath, filters: ['status' => 'paid']);
echo PHP_EOL . 'xlsx نوشته شد: ' . $xlsxPath . ' (' . \number_format(\filesize($xlsxPath) ?: 0) . ' بایت)' . PHP_EOL;
