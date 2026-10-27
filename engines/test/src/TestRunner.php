<?php

declare(strict_types=1);

namespace Tavaan\Testing;

use Tavaan\Core\Contracts\Console\OutputInterface;

/**
 * تَوان | Tavaan — Testing
 * TestRunner — Runner بومی صفر‌وابستگی (بدون PHPUnit — DOC-084E §7).
 *
 * کشف توسط `TestSuite` انجام می‌شود (فایل‌های `*Test.php` → کلاس‌های
 * `TestCase` → متدهای `test*` + گروه‌ها). خروجی رنگی و گزارش شکست با
 * فایل:خط (AC-TEST-01/05). برای اجرای موازی، `runSuite(..., progress: false)`
 * بی‌صدا اجرا شده و `TestResult` قابل سریال‌شدن برمی‌گرداند.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class TestRunner
{
    public function __construct(private readonly OutputInterface $output)
    {
    }

    /**
     * اجرای کامل: کشف → فیلتر → اجرا → گزارش انسانی.
     *
     * @param list<string> $paths  دایرکتوری/فایل‌های تست
     * @param string|null  $filter فیلتر نام (کلاس یا متد — case-insensitive)
     * @param string|null  $group  فقط گروه مشخص (`--group=cache`)
     *
     * @return int کد خروج (0 = همه سبز)
     */
    public function run(array $paths, ?string $filter = null, ?string $group = null): int
    {
        $start = \microtime(true);
        $suite = TestSuite::discover($paths)->filter($filter, $group);

        if ($suite->countClasses() === 0) {
            $this->output->writeln('  هیچ تستی یافت نشد.', 'comment');

            return 0;
        }

        $this->printHeader($suite);

        $result = $this->runSuite($suite);
        $wallMs = (\microtime(true) - $start) * 1000;

        $this->render($result, $wallMs);

        return $result->ok() ? 0 : 1;
    }

    /**
     * اجرای یک Suite آماده (بدون کشف/گزارش) — برای حالت ماشین/موازی.
     */
    public function runSuite(TestSuite $suite, bool $progress = true): TestResult
    {
        $result = new TestResult();

        foreach ($suite->classes() as $descriptor) {
            $this->runClass($descriptor, $result, $progress);
        }

        return $result;
    }

    /**
     * چاپ گزارش انسانی (شکست‌ها با فایل:خط + خلاصهٔ رنگی — AC-TEST-05).
     */
    public function render(TestResult $result, ?float $wallMs = null): void
    {
        $this->output->newLine();

        $failures = \array_values(\array_filter($result->failures(), static fn (TestFailure $f): bool => $f->kind === 'failure'));
        $errors = \array_values(\array_filter($result->failures(), static fn (TestFailure $f): bool => $f->kind === 'error'));

        if ($failures !== []) {
            $this->output->writeln('  Failures:', 'error');
            foreach ($failures as $i => $failure) {
                $this->printFailure($i + 1, $failure);
            }
            $this->output->newLine();
        }
        if ($errors !== []) {
            $this->output->writeln('  Errors:', 'error');
            foreach ($errors as $i => $failure) {
                $this->printFailure($i + 1, $failure);
            }
            $this->output->newLine();
        }

        $skipped = $result->failures();
        $skipped = \array_values(\array_filter($skipped, static fn (TestFailure $f): bool => $f->kind === 'skip'));
        if ($skipped !== []) {
            $this->output->writeln('  Skipped (با دلیل — محدودیت محیطی):', 'comment');
            foreach ($skipped as $i => $skip) {
                $this->output->writeln(\sprintf('  %d) %s — %s', $i + 1, $skip->test, $skip->message), 'dim');
            }
            $this->output->newLine();
        }

        $summaryArgs = [$result->total(), $result->passed(), $result->failed(), $result->errored()];
        $summaryFormat = '  Tests: %d, Passed: %d, Failed: %d, Errors: %d';
        if ($result->skipped() > 0) {
            $summaryArgs[] = $result->skipped();
            $summaryFormat .= ', Skipped: %d';
        }
        $summaryFormat .= ' — Assertions: %d — Time: %.0f ms — Memory: %.1f MB';
        $summaryArgs[] = $result->assertions();
        $summaryArgs[] = $wallMs ?? $result->durationMs();
        $summaryArgs[] = \memory_get_peak_usage(true) / 1048576;
        $summary = \vsprintf($summaryFormat, $summaryArgs);
        $this->output->writeln($summary, $result->ok() ? 'success' : 'error');
        $this->output->newLine();
    }

    // ─── internals ────────────────────────────────────────────────────────

    private function printHeader(TestSuite $suite): void
    {
        $this->output->newLine();
        $this->output->writeln('  🧪 tavaan test — Native Runner', 'bold');
        $this->output->writeln(\sprintf(
            '  %d test file(s), %d test class(es), %d test(s)',
            \count($suite->files()),
            $suite->countClasses(),
            $suite->countMethods(),
        ), 'dim');
        $this->output->newLine();
    }

    private function runClass(TestClassDescriptor $descriptor, TestResult $result, bool $progress): void
    {
        $shortName = $descriptor->shortName();
        if ($progress) {
            $this->output->write('  ' . \str_pad($shortName, 34));
        }

        foreach ($descriptor->methods as $method) {
            $fullName = $shortName . '::' . $method->name;

            /** @var TestCase $test */
            $test = new $descriptor->class();

            $start = \microtime(true);
            try {
                $assertions = $test->runBare($method->name);
                $result->recordPassed($assertions, (\microtime(true) - $start) * 1000);
                if ($progress) {
                    $this->output->write('.', 'info');
                }
            } catch (TestSkipped $e) {
                $result->recordSkipped($fullName, $e->getMessage(), (\microtime(true) - $start) * 1000);
                if ($progress) {
                    $this->output->write('S', 'comment');
                }
            } catch (AssertionFailed $e) {
                $result->recordFailure($fullName, $e, (\microtime(true) - $start) * 1000);
                if ($progress) {
                    $this->output->write('F', 'error');
                }
            } catch (\Throwable $e) {
                $result->recordError($fullName, $e, (\microtime(true) - $start) * 1000);
                if ($progress) {
                    $this->output->write('E', 'error');
                }
            }
        }

        if ($progress) {
            $this->output->newLine();
        }
    }

    private function printFailure(int $index, TestFailure $failure): void
    {
        $this->output->writeln(\sprintf('  %d) %s', $index, $failure->test), 'bold');
        if ($failure->kind === 'error') {
            $this->output->writeln('     [' . $failure->exception . '] ' . $failure->message, 'comment');
        } else {
            $this->output->writeln('     ' . \str_replace(\PHP_EOL, \PHP_EOL . '     ', $failure->message), 'comment');
        }
        if ($failure->location !== '') {
            $this->output->writeln('     at ' . $this->relativize($failure->location), 'dim');
        }
    }

    /** تبدیل مسیر مطلق به نسبی (بر پایهٔ TVN_BASE_PATH) برای خوانایی گزارش. */
    private function relativize(string $location): string
    {
        if (!\defined('TVN_BASE_PATH')) {
            return $location;
        }
        $base = \str_replace('\\', '/', \rtrim((string) \TVN_BASE_PATH, '/\\')) . '/';
        $normalized = \str_replace('\\', '/', $location);

        return \str_starts_with($normalized, $base) ? \substr($normalized, \strlen($base)) : $location;
    }
}
