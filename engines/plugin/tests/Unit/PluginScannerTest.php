<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Tests\Unit;

use Tavaan\Engine\Plugin\PluginScanner;
use Tavaan\Testing\TestCase;

/**
 * تست PluginScanner — اسکن توکنی توابع خطرناک (DOC-079 §5.1 — T-03).
 */
final class PluginScannerTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-scan-test-' . \getmypid();
        $this->clean($this->tmp);
        \mkdir($this->tmp . '/src', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->clean($this->tmp);
    }

    public function testDetectsDangerousGlobalCalls(): void
    {
        $this->write('src/bad.php', <<<'PHP'
        <?php
        namespace X;
        final class Bad {
            public function run(): void {
                eval('echo 1;');
                exec('ls -la');
                system('whoami');
                shell_exec('id');
                passthru('cat /etc/passwd');
                popen('x', 'r');
                proc_open('x', [], $p);
                curl_exec($ch);
                unserialize($data);
                file_get_contents('http://evil.example/x');
                fopen('/etc/passwd', 'r');
                assert('1==1');
                create_function('', '');
            }
        }
        PHP);

        $report = (new PluginScanner())->scan($this->tmp);

        self::assertFalse($report->clean());
        $funcs = \array_column($report->findings, 'func');
        foreach (['eval', 'exec', 'system', 'shell_exec', 'passthru', 'popen', 'proc_open', 'curl_exec', 'unserialize', 'file_get_contents', 'fopen', 'assert', 'create_function'] as $expected) {
            self::assertContains($expected, $funcs);
        }
        self::assertSame(1, $report->scannedFiles);
    }

    public function testDetectsIncludeRequire(): void
    {
        $this->write('src/inc.php', <<<'PHP'
        <?php
        include 'a.php';
        include_once 'b.php';
        require 'c.php';
        require_once 'd.php';
        PHP);

        $funcs = \array_column((new PluginScanner())->scan($this->tmp)->findings, 'func');

        self::assertContains('include', $funcs);
        self::assertContains('include_once', $funcs);
        self::assertContains('require', $funcs);
        self::assertContains('require_once', $funcs);
    }

    public function testMethodCallsAndDefinitions_AreNotFlagged(): void
    {
        $this->write('src/ok.php', <<<'PHP'
        <?php
        namespace Y;
        final class Runner {
            public function exec(string $job): void {}
            public function go(Processor $p): void {
                $p->exec('safe');
                $p?->exec('safe');
                self::system();
                static::assertTrueish();
            }
            private static function system(): void {}
            public static function assertTrueish(): void {}
        }
        PHP);

        $report = (new PluginScanner())->scan($this->tmp);

        self::assertTrue($report->clean(), 'مثبت کاذب: ' . \json_encode($report->findings));
    }

    public function testCleanPlugin_NoFindings(): void
    {
        $this->write('src/clean.php', <<<'PHP'
        <?php
        namespace Z;
        final class Calc {
            public function add(int $a, int $b): int { return $a + $b; }
        }
        PHP);

        $report = (new PluginScanner())->scan($this->tmp);

        self::assertTrue($report->clean());
        self::assertSame(1, $report->scannedFiles);
    }

    public function testScan_MissingDir_EmptyReport(): void
    {
        $report = (new PluginScanner())->scan($this->tmp . '/nonexistent');

        self::assertTrue($report->clean());
        self::assertSame(0, $report->scannedFiles);
    }

    public function testSummary_LimitsLines(): void
    {
        $code = "<?php\n";
        for ($i = 0; $i < 25; $i++) {
            $code .= "exec('x');\n";
        }
        $this->write('src/many.php', $code);

        $report = (new PluginScanner())->scan($this->tmp);

        self::assertSame(25, $report->count());
        self::assertCount(21, $report->summary(20));   // 20 + «و ۵ مورد دیگر»
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->tmp . '/' . $relative;
        $lines = \explode("\n", $contents);
        $out = [];
        foreach ($lines as $line) {
            $out[] = \preg_replace('/^ {8}/', '', $line) ?? $line;
        }
        \file_put_contents($path, \implode("\n", $out));
    }

    private function clean(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $item */
        foreach ($items as $item) {
            $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
        }
        \rmdir($dir);
    }
}
