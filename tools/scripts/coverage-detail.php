<?php

/**
 * تَوان | Tavaan — ابزار توسعه: گزارش per-file پوشش کد.
 *
 * یک wrapper روی `bin/tvn test` با xdebug coverage که به‌جای جمع‌بندی
 * حوزه‌ای (CoverageReporter)، بدترین **فایل‌ها** را فهرست می‌کند — برای
 * هدف‌گیری اسپرینت‌های پوشش (DOC-110 §9).
 *
 * استفاده:
 *   php -d xdebug.mode=coverage tools/scripts/coverage-detail.php [filter-path] -- [test args]
 * مثال:
 *   php -d xdebug.mode=coverage tools/scripts/coverage-detail.php engines/console -- test engines/console
 */

declare(strict_types=1);

if (!\extension_loaded('xdebug') || !\function_exists('xdebug_start_code_coverage')) {
    fwrite(STDERR, "xdebug (mode=coverage) لازم است.\n");
    exit(2);
}

$filter = '';
$testArgs = [];
$argvList = \array_slice($argv, 1);
$sep = \array_search('--', $argvList, true);
if ($sep === false) {
    $filter = (string) ($argvList[0] ?? '');
} else {
    $filter = (string) ($argvList[0] ?? '');
    $testArgs = \array_slice($argvList, $sep + 1);
}
if ($testArgs === []) {
    $testArgs = ['test'];
}

xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

register_shutdown_function(static function () use ($filter): void {
    $coverage = xdebug_get_code_coverage();
    xdebug_stop_code_coverage(false);

    $base = \str_replace('\\', '/', \dirname(__DIR__, 2));
    $rows = [];
    foreach ($coverage as $file => $lines) {
        $normalized = \str_replace('\\', '/', (string) $file);
        if (!\str_starts_with($normalized, $base . '/')) {
            continue;
        }
        $relative = \substr($normalized, \strlen($base) + 1);
        $isTarget = \preg_match('#^(core/|app/|engines/[a-z0-9\-]+/src/)#', $relative) === 1;
        if (!$isTarget) {
            continue;
        }
        if ($filter !== '' && !\str_starts_with($relative, $filter)) {
            continue;
        }
        $covered = 0;
        $total = 0;
        foreach ($lines as $status) {
            if ($status === -2) {
                continue;   // خط غیراجرایی
            }
            $total++;
            if ($status === 1) {
                $covered++;
            }
        }
        if ($total === 0) {
            continue;
        }
        $rows[] = ['file' => $relative, 'covered' => $covered, 'total' => $total, 'pct' => $covered / $total * 100];
    }

    \usort($rows, static fn (array $a, array $b): int => $a['pct'] <=> $b['pct'] ?: $b['total'] <=> $a['total']);

    echo "\n── بدترین فایل‌ها (per-file coverage" . ($filter !== '' ? " — filter: {$filter}" : '') . ") ──\n";
    foreach (\array_slice($rows, 0, 60) as $row) {
        printf("  %6.1f%%  %5d/%-5d  %s\n", $row['pct'], $row['covered'], $row['total'], $row['file']);
    }
    $tc = \array_sum(\array_column($rows, 'covered'));
    $tt = \array_sum(\array_column($rows, 'total'));
    printf("  TOTAL(filter): %d/%d — %.1f%% در %d فایل\n", $tc, $tt, $tt === 0 ? 0 : $tc / $tt * 100, \count($rows));
});

// اجرای runner در همین فرایند (تا coverage جمع شود)
$argv = \array_merge(['bin/tvn'], $testArgs);
require \dirname(__DIR__, 2) . '/bin/tvn';
