<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Testing\Coverage\CoverageReporter;
use Tavaan\Testing\TestResult;
use Tavaan\Testing\TestRunner;
use Tavaan\Testing\TestSuite;

/**
 * اجرای تست‌ها با Native Runner (بدون PHPUnit — DOC-084E §7).
 *
 * ```bash
 * tvn test                          # همه
 * tvn test --group=cache            # یک گروه (slug موتور / unit / feature / #[Group])
 * tvn test --filter=ProductTest     # فیلتر نام
 * tvn test engines/cache            # مسیر خاص
 * tvn test --coverage               # با Xdebug/PCOV (اختیاری)
 * tvn test --parallel=4             # چهار فرایند موازی
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class TestCommand extends Command
{
    protected string $signature = 'test {filter? : فیلتر نام تست/کلاس یا مسیر} {paths?* : مسیر(های) خاص تست}'
        . ' {--engines : فقط تست موتورها} {--app : فقط تست‌های سراسری}'
        . ' {--filter= : فیلتر نام تست/کلاس} {--group= : فقط گروه مشخص} {--coverage : پوشش کد (xdebug/pcov)}'
        . ' {--parallel=1 : تعداد فرایندهای موازی} {--machine= : داخلی: manifest فرزند موازی} {--result-out= : داخلی: خروجی JSON فرزند}';

    protected string $description = 'اجرای مجموعه تست (Native Runner صفر‌وابستگی)';

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';

        $bootstrap = $base . '/tests/bootstrap.php';
        if (\is_file($bootstrap)) {
            require_once $bootstrap;
        }

        // حالت داخلی: فرزندِ اجرای موازی (machine mode — بی‌صدا، خروجی JSON)
        $manifest = $this->option('machine');
        $resultOut = $this->option('result-out');
        if (\is_string($manifest) && $manifest !== '' && \is_string($resultOut) && $resultOut !== '') {
            return $this->runChild($manifest, $resultOut);
        }

        [$paths, $filter, $group] = $this->resolveScope($base);
        $runner = new TestRunner($this->output);

        $parallel = \max(1, (int) $this->option('parallel'));
        if ($parallel > 1) {
            if ($this->option('coverage')) {
                $this->output->writeln('  ⚠️  coverage در حالت --parallel پشتیبانی نمی‌شود — بدون پوشش اجرا می‌شود.', 'comment');
            }

            return $this->runParallel($runner, $paths, $filter, $group, $parallel, $base) === 0
                ? self::SUCCESS
                : self::FAILURE;
        }

        $coverage = null;
        if ($this->option('coverage')) {
            $coverage = new CoverageReporter();
            $coverage->start($this->output);
        }

        $code = $runner->run($paths, $filter, $group);

        $coverage?->report($this->output, $base);

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }

    // ─── دامنهٔ اجرا ──────────────────────────────────────────────────────

    /**
     * استخراج مسیرها/فیلتر/گروه از آرگومان‌ها.
     *
     * اولین positional هم فیلتر است و هم مسیر — اگر به فایل/دایرکتوری موجود
     * نگاشت شود، مسیر تلقی می‌شود (`tvn test engines/cache`).
     *
     * @return array{0: list<string>, 1: ?string, 2: ?string}
     */
    private function resolveScope(string $base): array
    {
        $group = $this->option('group');
        $group = \is_string($group) && $group !== '' ? $group : null;

        $paths = [];
        $extra = $this->argument('paths');
        if (\is_array($extra)) {
            foreach ($extra as $path) {
                $paths[] = self::absolute((string) $path, $base);
            }
        }

        $filter = null;
        $first = $this->argument('filter');
        if (\is_string($first) && $first !== '') {
            $candidate = self::absolute($first, $base);
            if (\is_dir($candidate) || \is_file($candidate)) {
                // positional اول مسیر است → ابتدای فهرست (حتی اگر paths* هم پر باشد)
                \array_unshift($paths, $candidate);
            } else {
                $filter = $first;
            }
        }

        // شکل `--filter=X` (DOC-084E §7) بر positional اولویت دارد
        $filterOption = $this->option('filter');
        if (\is_string($filterOption) && $filterOption !== '') {
            $filter = $filterOption;
        }

        if ($paths === []) {
            if (!$this->option('engines')) {
                $paths[] = $base . '/tests/Unit';
                $paths[] = $base . '/tests/Feature';
            }
            if (!$this->option('app')) {
                foreach (\glob($base . '/engines/*/tests') ?: [] as $dir) {
                    $paths[] = $dir;
                }
                foreach (\glob($base . '/modules/*/tests') ?: [] as $dir) {
                    $paths[] = $dir;
                }
            }
        }

        return [$paths, $filter, $group];
    }

    private static function absolute(string $path, string $base): string
    {
        if ($path === '') {
            return $base;
        }
        // مسیر مطلق (unix `/…` یا windows `C:\…`)
        if (\str_starts_with($path, '/') || \preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return $base . '/' . \ltrim($path, '/\\');
    }

    // ─── اجرای موازی ──────────────────────────────────────────────────────

    /**
     * تقسیم Suite به N دسته و اجرای فرزندان `php bin/tvn test --machine=…`.
     *
     * @param list<string> $paths
     */
    private function runParallel(TestRunner $runner, array $paths, ?string $filter, ?string $group, int $parallel, string $base): int
    {
        $suite = TestSuite::discover($paths)->filter($filter, $group);
        if ($suite->countClasses() === 0) {
            $this->output->writeln('  هیچ تستی یافت نشد.', 'comment');

            return 0;
        }

        $chunks = $suite->chunkFiles($parallel);
        $this->output->newLine();
        $this->output->writeln('  🧪 tavaan test — Native Runner (parallel ×' . \count($chunks) . ')', 'bold');
        $this->output->writeln(\sprintf(
            '  %d test file(s), %d test(s)',
            \count($suite->files()),
            $suite->countMethods(),
        ), 'dim');
        $this->output->newLine();

        $start = \microtime(true);
        $processes = $this->spawnChildren($chunks, $base);

        [$merged, $crashed] = $this->collectChildren($processes);
        $runner->render($merged, (\microtime(true) - $start) * 1000);

        return $merged->ok() && !$crashed ? 0 : 1;
    }

    /**
     * @param list<list<string>> $chunks
     *
     * @return list<array{process: resource|false, manifest: string, result: string, stderrFile: string, nullFile: string, stderr: resource|false}>
     */
    private function spawnChildren(array $chunks, string $base): array
    {
        $processes = [];
        $pid = \getmypid();

        foreach ($chunks as $index => $files) {
            $manifestFile = (string) \tempnam(\sys_get_temp_dir(), 'tvn-manifest-');
            $resultFile = (string) \tempnam(\sys_get_temp_dir(), 'tvn-result-');
            $stderrFile = (string) \tempnam(\sys_get_temp_dir(), 'tvn-stderr-');
            $nullFile = \sys_get_temp_dir() . '/tvn-null-' . $pid . '-' . $index;
            \file_put_contents($manifestFile, (string) \json_encode(['files' => $files]));

            $stderr = \fopen($stderrFile, 'wb');
            $process = \proc_open(
                [PHP_BINARY, $base . '/bin/tvn', 'test', '--machine=' . $manifestFile, '--result-out=' . $resultFile],
                [1 => ['file', $nullFile, 'w'], 2 => $stderr === false ? ['file', $nullFile, 'w'] : $stderr],
                $pipes,
                $base,
            );

            $processes[] = [
                'process' => $process,
                'manifest' => $manifestFile,
                'result' => $resultFile,
                'stderrFile' => $stderrFile,
                'nullFile' => $nullFile,
                'stderr' => $stderr,
            ];
        }

        return $processes;
    }

    /**
     * @param list<array{process: resource|false, manifest: string, result: string, stderrFile: string, nullFile: string, stderr: resource|false}> $processes
     *
     * @return array{0: TestResult, 1: bool}
     */
    private function collectChildren(array $processes): array
    {
        $merged = new TestResult();
        $crashed = false;

        foreach ($processes as $index => $child) {
            $exitCode = -1;
            if (\is_resource($child['process'])) {
                $exitCode = \proc_close($child['process']);
            } else {
                $crashed = true;
            }
            if (\is_resource($child['stderr'])) {
                \fclose($child['stderr']);
            }

            $payload = \is_file($child['result']) ? \file_get_contents($child['result']) : '';
            $decoded = \is_string($payload) && $payload !== '' ? \json_decode($payload, true) : null;

            if (\is_array($decoded)) {
                $merged->merge(TestResult::fromArray($decoded));
            } else {
                $crashed = true;
                $stderr = \is_file($child['stderrFile']) ? (string) \file_get_contents($child['stderrFile']) : '';
                $this->output->writeln(\sprintf('  ✗ فرزند #%d با کد %d شکست خورد (بدون خروجی JSON).', $index + 1, $exitCode), 'error');
                if ($stderr !== '') {
                    $this->output->writeln('    ' . \str_replace(\PHP_EOL, \PHP_EOL . '    ', \rtrim($stderr)), 'comment');
                }
            }

            @\unlink($child['manifest']);
            @\unlink($child['result']);
            @\unlink($child['stderrFile']);
            @\unlink($child['nullFile']);
        }

        return [$merged, $crashed];
    }

    /**
     * فرزند موازی: اجرای بی‌صدای فایل‌های manifest و نوشتن نتیجهٔ JSON.
     */
    private function runChild(string $manifest, string $resultOut): int
    {
        $payload = \file_get_contents($manifest);
        $data = \is_string($payload) ? \json_decode($payload, true) : null;
        $files = \is_array($data) ? (array) ($data['files'] ?? []) : [];

        /** @var list<string> $fileList */
        $fileList = \array_values(\array_filter($files, 'is_string'));
        $suite = TestSuite::fromFiles($fileList);
        $runner = new TestRunner($this->output);
        $result = $runner->runSuite($suite, progress: false);

        \file_put_contents($resultOut, (string) \json_encode($result->toArray()));

        return $result->ok() ? self::SUCCESS : self::FAILURE;
    }
}
