<?php

declare(strict_types=1);

namespace Tavaan\Testing;

use Tavaan\Core\Contracts\Console\OutputInterface;

/**
 * تَوان | Tavaan — Testing
 * TestRunner — Runner بومی صفر‌وابستگی (بدون PHPUnit — DOC-084E §7).
 *
 * کشف: فایل‌های `*Test.php` در مسیرهای داده‌شده → کلاس‌های TestCase → متدهای `test*`.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class TestRunner
{
    private int $passed = 0;

    private int $failed = 0;

    private int $assertions = 0;

    /** @var list<array{test: string, error: string, trace: string}> */
    private array $failures = [];

    public function __construct(private readonly OutputInterface $output)
    {
    }

    /**
     * @param list<string> $paths  دایرکتوری/فایل‌های تست
     * @param string|null  $filter فیلتر نام (کلاس یا متد)
     *
     * @return int کد خروج (0 = همه سبز)
     */
    public function run(array $paths, ?string $filter = null): int
    {
        $start = \microtime(true);
        $files = $this->discover($paths);

        if ($files === []) {
            $this->output->writeln('  هیچ فایلی یافت نشد.', 'comment');

            return 0;
        }

        $this->output->newLine();
        $this->output->writeln('  🧪 tavaan test — Native Runner', 'bold');
        $this->output->writeln('  ' . \count($files) . ' test file(s)', 'dim');
        $this->output->newLine();

        foreach ($files as $file) {
            $classes = $this->loadTestClasses($file);

            foreach ($classes as $class) {
                $this->runClass($class, $filter);
            }
        }

        $elapsed = (\microtime(true) - $start) * 1000;

        $this->output->newLine();
        if ($this->failures !== []) {
            $this->output->writeln('  Failures:', 'error');
            foreach ($this->failures as $i => $failure) {
                $this->output->writeln(\sprintf('  %d) %s', $i + 1, $failure['test']), 'bold');
                $this->output->writeln('     ' . $failure['error'], 'comment');
                if ($failure['trace'] !== '') {
                    $this->output->writeln('     ' . $failure['trace'], 'dim');
                }
            }
            $this->output->newLine();
        }

        $summary = \sprintf(
            '  Tests: %d passed, %d failed — Assertions: %d — Time: %.0f ms — Memory: %.1f MB',
            $this->passed,
            $this->failed,
            $this->assertions,
            $elapsed,
            \memory_get_peak_usage(true) / 1048576,
        );
        $this->output->writeln($summary, $this->failed === 0 ? 'success' : 'error');
        $this->output->newLine();

        return $this->failed === 0 ? 0 : 1;
    }

    /** @param class-string<TestCase> $class */
    private function runClass(string $class, ?string $filter): void
    {
        $reflection = new \ReflectionClass($class);
        $methods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (\str_starts_with($method->getName(), 'test')) {
                $methods[] = $method->getName();
            }
        }

        if ($methods === []) {
            return;
        }

        $shortName = $reflection->getShortName();
        $this->output->write('  ' . \str_pad($shortName, 34));

        foreach ($methods as $method) {
            $fullName = $shortName . '::' . $method;
            if ($filter !== null && !\str_contains(\strtolower($fullName), \strtolower($filter))) {
                continue;
            }

            /** @var TestCase $test */
            $test = new $class();

            try {
                $this->assertions += $test->runBare($method);
                $this->passed++;
                $this->output->write('.', 'info');
            } catch (AssertionFailed $e) {
                $this->failed++;
                $this->output->write('F', 'error');
                $this->failures[] = ['test' => $fullName, 'error' => $e->getMessage(), 'trace' => ''];
            } catch (\Throwable $e) {
                $this->failed++;
                $this->output->write('E', 'error');
                $this->failures[] = [
                    'test' => $fullName,
                    'error' => '[' . $e::class . '] ' . $e->getMessage(),
                    'trace' => $e->getFile() . ':' . $e->getLine(),
                ];
            }
        }

        $this->output->newLine();
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function discover(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (\is_file($path)) {
                $files[] = $path;
                continue;
            }
            if (!\is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && \str_ends_with($file->getFilename(), 'Test.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        \sort($files);   // 🔒 ترتیب قطعی

        return $files;
    }

    /**
     * @return list<class-string<TestCase>>
     */
    private function loadTestClasses(string $file): array
    {
        $before = \get_declared_classes();
        require_once $file;
        $after = \get_declared_classes();

        $classes = [];
        foreach (\array_diff($after, $before) as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isSubclassOf(TestCase::class) && !$reflection->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
