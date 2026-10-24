<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * `tvn bench:all` — اجرای همهٔ بنچمارک‌های موتورها و جمع‌بندی نتایج
 * در برابر اهداف DOC-112 (نشانگرهای ✅/⚠️/❌ در خروجی benchها).
 *
 * `--fail-on-regression` → exit 1 اگر هیچ ❌ دیده شود (برای CI).
 *
 * 🔒 ممیزی M0–M8 (R2): وعدهٔ DOC-083/DOC-112 بود و پیاده‌سازی نشده بود.
 *
 * @api 1.0
 * @see docs/07-quality/benchmark-targets.md
 */
final class BenchAllCommand extends Command
{
    protected string $signature = 'bench:all {--filter= : فقط benchهای شامل این زیررشته} {--fail-on-regression : exit 1 روی هر ❌} {--json : خروجی ماشین‌خوان}';

    protected string $description = 'اجرای همهٔ بنچمارک‌ها و جمع‌بندی در برابر اهداف DOC-112';

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 4);
        $filter = (string) ($this->option('filter') ?: '');
        $failOnRegression = (bool) $this->option('fail-on-regression');
        $asJson = (bool) $this->option('json');

        $benches = [];
        foreach ((array) (\glob($base . '/engines/*/benchmarks/*.bench.php') ?: []) as $file) {
            $benches[] = (string) $file;
        }
        foreach ((array) (\glob($base . '/tools/scripts/bench-*.php') ?: []) as $file) {
            if (!\str_contains((string) $file, '-child')) {
                $benches[] = (string) $file;
            }
        }
        \sort($benches);
        if ($filter !== '') {
            $benches = \array_values(\array_filter($benches, static fn (string $b): bool => \str_contains($b, $filter)));
        }

        if ($benches === []) {
            $this->warn('  هیچ bench یافت نشد.');

            return self::SUCCESS;
        }

        $results = [];
        $totalFail = 0;
        $totalWarn = 0;

        foreach ($benches as $bench) {
            $name = \basename($bench, '.php');
            if (!$asJson) {
                $this->comment('  ▶ ' . $name . ' …');
            }
            $output = $this->runBench($bench);
            $fails = \substr_count($output, '❌');
            $warns = \substr_count($output, '⚠️');
            $totalFail += $fails;
            $totalWarn += $warns;
            $results[] = ['bench' => $name, 'fail' => $fails, 'warn' => $warns, 'output' => $output];
            if (!$asJson) {
                foreach (\explode("\n", \trim($output)) as $line) {
                    if (\trim($line) !== '') {
                        $this->line('    ' . \trim($line));
                    }
                }
            }
        }

        if ($asJson) {
            $this->line((string) \json_encode([
                'ok' => $totalFail === 0,
                'totals' => ['fail' => $totalFail, 'warn' => $totalWarn],
                'results' => \array_map(static fn (array $r): array => ['bench' => $r['bench'], 'fail' => $r['fail'], 'warn' => $r['warn']], $results),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return ($failOnRegression && $totalFail > 0) ? self::FAILURE : self::SUCCESS;
        }

        $rows = [];
        foreach ($results as $r) {
            $rows[] = [$r['bench'], (string) $r['fail'], (string) $r['warn'], $r['fail'] > 0 ? '❌' : ($r['warn'] > 0 ? '⚠️' : '✅')];
        }
        $this->table(['bench', '❌', '⚠️', 'وضعیت'], $rows);
        $this->line('');
        $this->info('  جمع: ' . \count($results) . ' bench — ❌ ' . $totalFail . '، ⚠️ ' . $totalWarn
            . ' (توجه: نتایج µs/ms وابسته به سخت‌افزار ماشین است)');

        if ($failOnRegression && $totalFail > 0) {
            $this->error('  ✖ ' . $totalFail . ' هدف DOC-112 نقض شد.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function runBench(string $bench): string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @\proc_open([PHP_BINARY, '-d', 'memory_limit=512M', $bench], $descriptors, $pipes);
        if (!\is_resource($process)) {
            return 'proc_open failed';
        }
        $out = (string) \stream_get_contents($pipes[1]);
        $err = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        \proc_close($process);

        return $out . ($err !== '' ? "\n" . $err : '');
    }
}
