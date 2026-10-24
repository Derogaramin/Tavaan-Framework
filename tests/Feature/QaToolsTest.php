<?php

declare(strict_types=1);

namespace Tavaan\Tests\Feature;

use Tavaan\Testing\TestCase;

/**
 * ابزارهای QA افزوده‌شده در ممیزی M0–M8 (یافتهٔ H2/H3):
 * arch:check، security:scan، lint:php، env:check، engine:validate،
 * down/up و module:enable/disable — تست end-to-end از طریق CLI واقعی
 * (subprocess) چون این دستورات ابزارِ خودِ فریمورک‌اند.
 */
final class QaToolsTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 2);
    }

    /** @return array{0: int, 1: string} [exitCode, output] */
    private function tvn(string $args): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd = 'cd ' . \escapeshellarg($this->base) . ' && ' . \escapeshellarg(PHP_BINARY) . ' bin/tvn ' . $args . ' 2>&1';
        $process = \proc_open($cmd, $descriptors, $pipes);
        $this->assertIsResource($process);
        /** @var resource $process */
        $out = (string) \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $code = \proc_close($process);

        return [$code, $out];
    }

    public function testArchCheckPassesOnRepository(): void
    {
        [$code, $out] = $this->tvn('arch:check');
        $this->assertSame(0, $code, 'arch:check باید روی مخزن پاک پاس شود: ' . $out);
        $this->assertStringContainsString('✓', $out);
    }

    public function testArchCheckJsonMode(): void
    {
        [$code, $out] = $this->tvn('arch:check --json');
        $this->assertSame(0, $code);
        /** @var mixed $decoded */
        $decoded = \json_decode($out, true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['ok']);
        $this->assertSame([], $decoded['violations']);
    }

    public function testSecurityScanNoHighFindings(): void
    {
        [$code, $out] = $this->tvn('security:scan --json');
        $this->assertSame(0, $code, 'security:scan باید بدون یافتهٔ high پاس شود: ' . \substr($out, 0, 800));
        /** @var mixed $decoded */
        $decoded = \json_decode($out, true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['ok']);
        foreach ((array) $decoded['findings'] as $finding) {
            $this->assertNotSame('high', $finding['severity'] ?? null);
        }
    }

    public function testSecurityScanDetectsEvalInGivenPath(): void
    {
        $dir = \sys_get_temp_dir() . '/tvn-sec-scan-' . \bin2hex(\random_bytes(4));
        \mkdir($dir, 0o775, true);
        \file_put_contents($dir . '/evil.php', "<?php\nfunction bad(string \$x): mixed { return eval(\$x); }\n");
        try {
            [$code, $out] = $this->tvn('security:scan --json ' . \escapeshellarg($dir));
            $this->assertSame(1, $code);
            /** @var mixed $decoded */
            $decoded = \json_decode($out, true);
            $ids = \array_column((array) ($decoded['findings'] ?? []), 'id');
            $this->assertContains('SEC-EVAL', $ids);
        } finally {
            @\unlink($dir . '/evil.php');
            @\rmdir($dir);
        }
    }

    public function testLintPhpOnTempFiles(): void
    {
        $dir = \sys_get_temp_dir() . '/tvn-lint-' . \bin2hex(\random_bytes(4));
        \mkdir($dir, 0o775, true);
        \file_put_contents($dir . '/good.php', "<?php\ndeclare(strict_types=1);\nfunction ok(): int { return 1; }\n");
        \file_put_contents($dir . '/bad.php', "<?php\nfunction broken( { \n");
        try {
            [$code, $out] = $this->tvn('lint:php --json ' . \escapeshellarg($dir));
            $this->assertSame(1, $code);
            /** @var mixed $decoded */
            $decoded = \json_decode($out, true);
            $this->assertIsArray($decoded);
            $this->assertFalse($decoded['ok']);
            $this->assertSame(2, $decoded['linted']);
            $this->assertCount(1, $decoded['failures']);

            [$code2] = $this->tvn('lint:php --json ' . \escapeshellarg($dir . '/good.php'));
            $this->assertSame(0, $code2);
        } finally {
            @\unlink($dir . '/good.php');
            @\unlink($dir . '/bad.php');
            @\rmdir($dir);
        }
    }

    public function testEnvCheckReportsDrift(): void
    {
        [$code, $out] = $this->tvn('env:check --json');
        $this->assertSame(0, $code, $out);
        /** @var mixed $decoded */
        $decoded = \json_decode($out, true);
        $this->assertIsArray($decoded);
        $this->assertContains('TVN_APP_NAME', $decoded['used']);
        $this->assertContains('TVN_AUTOLOAD_CACHE', $decoded['used']); // میکرواسکن bootstrap
    }

    public function testEngineValidatePasses(): void
    {
        [$code, $out] = $this->tvn('engine:validate --json');
        $this->assertSame(0, $code, \substr($out, 0, 600));
        /** @var mixed $decoded */
        $decoded = \json_decode($out, true);
        $this->assertTrue($decoded['ok']);
        $this->assertGreaterThanOrEqual(52, $decoded['checked']);
    }

    public function testDownUpRoundTrip(): void
    {
        $downFile = $this->base . '/storage/framework/down';
        if (\is_file($downFile)) {
            @\unlink($downFile);   // پاک‌سازی بازماندهٔ اجرای قبلی
        }
        {
            [$code] = $this->tvn('down --message=test --retry=60');
            $this->assertSame(0, $code);
            $this->assertFileExists($downFile);
            /** @var array<string, mixed> $data */
            $data = (array) \json_decode((string) \file_get_contents($downFile), true);
            $this->assertSame('test', $data['message']);
            $this->assertSame(60, $data['retry']);

            [$code2] = $this->tvn('up');
            $this->assertSame(0, $code2);
            $this->assertFileDoesNotExist($downFile);
        }
    }

    public function testModuleEnableDisableErrors(): void
    {
        [$code, $out] = $this->tvn('module:disable no-such-module');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('یافت نشد', $out);   // ترجمهٔ fa، نه messageId خام

        // غیرفعال‌سازی موتور دارای وابسته → خطای hasDependents (بدون تغییر وضعیت)
        [$code2, $out2] = $this->tvn('module:disable http');
        $this->assertSame(1, $code2);
    }

    public function testBenchAllAggregates(): void
    {
        [$code, $out] = $this->tvn('bench:all --filter=render --json');
        $this->assertSame(0, $code, \substr($out, 0, 400));
        /** @var mixed $decoded */
        $decoded = \json_decode($out, true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded['results']);
        $this->assertSame('render.bench', $decoded['results'][0]['bench']);
    }
}
