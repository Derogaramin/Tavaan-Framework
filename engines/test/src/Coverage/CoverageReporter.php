<?php

declare(strict_types=1);

namespace Tavaan\Testing\Coverage;

use Tavaan\Core\Contracts\Console\OutputInterface;

/**
 * تَوان | Tavaan — Testing
 * CoverageReporter — پوشش خط با Xdebug/PCOV (اختیاری — DOC-084E §9).
 *
 * اگر هیچ‌کدام نصب نباشد: هشدار و ادامهٔ اجرا بدون coverage (طبق سند).
 * فقط فایل‌های `core/` و `engines/<slug>/src/` و `app/` شمارش می‌شوند.
 * فراخوانی افزونه‌ها به‌صورت dynamic-string است (بدون وابستگی compile-time).
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class CoverageReporter
{
    /** xdebug flags (در نبود ثابت‌ها): CC_UNUSED|CC_DEAD_CODE */
    private const XDEBUG_FLAGS = 3;

    private bool $collecting = false;

    /** درایور فعال: `pcov`|`xdebug`|null */
    public static function detectDriver(): ?string
    {
        if (\extension_loaded('pcov') && \function_exists('pcov\\start')) {
            return 'pcov';
        }
        if (\extension_loaded('xdebug') && \function_exists('xdebug_start_code_coverage')) {
            return 'xdebug';
        }

        return null;
    }

    /**
     * آغاز جمع‌آوری؛ اگر درایوری نباشد هشدار می‌دهد و false برمی‌گرداند.
     */
    public function start(OutputInterface $output): bool
    {
        $driver = self::detectDriver();
        if ($driver === null) {
            $output->writeln('  ⚠️  coverage نیازمند افزونهٔ xdebug (mode=coverage) یا pcov است — بدون پوشش ادامه می‌یابد.', 'comment');

            return false;
        }

        try {
            if ($driver === 'pcov') {
                $start = 'pcov\\start';
                $start();
            } else {
                $flags = (\defined('XDEBUG_CC_UNUSED') ? (int) \constant('XDEBUG_CC_UNUSED') : 1)
                    | (\defined('XDEBUG_CC_DEAD_CODE') ? (int) \constant('XDEBUG_CC_DEAD_CODE') : 2);
                $start = 'xdebug_start_code_coverage';
                $start($flags);
            }
            $this->collecting = true;

            return true;
        } catch (\Throwable $e) {
            $output->writeln('  ⚠️  شروع coverage ناموفق بود (' . $e->getMessage() . ') — بدون پوشش ادامه می‌یابد.', 'comment');

            return false;
        }
    }

    /**
     * پایان جمع‌آوری و چاپ گزارش (به تفکیک core/هر موتور — بدترین‌ها نخست).
     */
    public function report(OutputInterface $output, string $basePath): void
    {
        if (!$this->collecting) {
            return;
        }
        $this->collecting = false;

        try {
            $coverage = $this->collect();
        } catch (\Throwable $e) {
            $output->writeln('  ⚠️  جمع‌آوری coverage ناموفق بود: ' . $e->getMessage(), 'comment');

            return;
        }

        $areas = $this->aggregate($coverage, $basePath);
        if ($areas === []) {
            $output->writeln('  ⚠️  هیچ فایلی زیر core/ یا engines/*/src/ پوشش داده نشد.', 'comment');

            return;
        }

        \uasort($areas, static fn (array $a, array $b): int => $a['percent'] <=> $b['percent']);

        $rows = [];
        $totalCovered = 0;
        $totalLines = 0;
        foreach ($areas as $area => $stats) {
            $totalCovered += $stats['covered'];
            $totalLines += $stats['total'];
            $rows[] = [$area, $stats['covered'] . '/' . $stats['total'], \sprintf('%.1f%%', $stats['percent'])];
        }

        $output->newLine();
        $output->writeln('  📊 Code Coverage (خطوط اجرایی)', 'bold');
        $output->table(['حوزه', 'پوشش/کل', 'درصد'], $rows);
        $output->writeln(\sprintf(
            '  TOTAL: %d/%d — %.1f%%',
            $totalCovered,
            $totalLines,
            $totalLines === 0 ? 0.0 : $totalCovered / $totalLines * 100,
        ), 'info');
        $output->newLine();
    }

    /**
     * @return array<string, array<int, int>> file → line → status (1|-1|-2)
     */
    private function collect(): array
    {
        if (self::detectDriver() === 'pcov') {
            // pcov\all = 2 (همهٔ خطوط دیده‌شده؛ بدون فیلتر)
            $type = \defined('pcov\\all') ? (int) \constant('pcov\\all') : 2;
            $collect = 'pcov\\collect';
            $data = $collect($type);
            $stop = 'pcov\\stop';
            $stop();

            return \is_array($data) ? $data : [];
        }

        $get = 'xdebug_get_code_coverage';
        $data = $get();
        $stop = 'xdebug_stop_code_coverage';
        $stop(false);

        return \is_array($data) ? $data : [];
    }

    /**
     * جمع‌بندی به تفکیک حوزه (core / engines/<slug> / app).
     *
     * @param array<string, array<int, int>> $coverage
     *
     * @return array<string, array{covered: int, total: int, percent: float}>
     */
    private function aggregate(array $coverage, string $basePath): array
    {
        $base = \str_replace('\\', '/', \rtrim($basePath, '/\\'));
        $areas = [];

        foreach ($coverage as $file => $lines) {
            $normalized = \str_replace('\\', '/', (string) $file);
            if (!\str_starts_with($normalized, $base . '/')) {
                continue;
            }
            $relative = \substr($normalized, \strlen($base) + 1);

            $area = match (true) {
                \str_starts_with($relative, 'core/') => 'core',
                \str_starts_with($relative, 'app/') => 'app',
                \preg_match('#^engines/([a-z0-9\-]+)/src/#', $relative, $m) === 1 => 'engines/' . $m[1],
                default => null,
            };
            if ($area === null) {
                continue;
            }

            $covered = 0;
            $total = 0;
            foreach ((array) $lines as $status) {
                $status = (int) $status;
                if ($status === -2) {
                    continue;   // خط غیرقابل‌اجرا (dead-code flag)
                }
                $total++;
                if ($status === 1) {
                    $covered++;
                }
            }

            $areas[$area]['covered'] = ($areas[$area]['covered'] ?? 0) + $covered;
            $areas[$area]['total'] = ($areas[$area]['total'] ?? 0) + $total;
        }

        foreach ($areas as $area => $stats) {
            $areas[$area] = [
                'covered' => $stats['covered'],
                'total' => $stats['total'],
                'percent' => $stats['total'] === 0 ? 0.0 : $stats['covered'] / $stats['total'] * 100,
            ];
        }

        return $areas;
    }
}
