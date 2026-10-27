<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Core\Contracts\Console\KernelInterface;
use Tavaan\Engine\Console\Input\ArgvInput;
use Tavaan\Engine\Console\Output\Output;
use Tavaan\Testing\TestCase;

/**
 * تست فرمان‌های QA/ابزار به‌صورت in-process (پوشش real-code مسیرهای
 * handle() — QaToolsTest همان‌ها را subprocess اجرا می‌کند و coverage
 * درون‌فرایندی ثبت نمی‌شود).
 *
 * 🔒 هیچ فرمان مخربی روی ریپو اجرا نمی‌شود: lint/security روی temp dir،
 * down/up چرخهٔ کامل با پاک‌سازی، بقیه read-only.
 */
final class QaCommandsTest extends TestCase
{
    private ?Application $appInstance = null;

    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-qa-cmd-' . \getmypid() . '-' . \bin2hex(\random_bytes(4));
        \mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        // اطمینان از خروج از حالت تعمیر حتی در صورت شکست تست
        $down = TVN_BASE_PATH . '/storage/framework/down';
        if (\is_file($down)) {
            @\unlink($down);
        }
        $this->rrmdir($this->tmp);
    }

    /**
     * اجرای فرمان در همان فرایند با خروجی حافظه‌ای.
     *
     * @return array{code: int, out: string}
     */
    private function run(string ...$argv): array
    {
        $app = $this->appInstance ??= ApplicationBuilder::create((string) TVN_BASE_PATH)->forConsole()->boot();

        /** @var KernelInterface $kernel */
        $kernel = $app->make(KernelInterface::class);

        $stream = \fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $output = new Output($stream);

        $code = $kernel->handle(new ArgvInput($argv), $output);

        \rewind($stream);
        $out = (string) \stream_get_contents($stream);
        \fclose($stream);

        return ['code' => $code, 'out' => $out];
    }

    // ─── lint:php ──────────────────────────────────────────────────────────

    public function testLintPhpPassesOnCleanFiles(): void
    {
        \file_put_contents($this->tmp . '/clean.php', "<?php\n\ndeclare(strict_types=1);\n\nfinal class QaClean {}\n");

        $result = $this->run('lint:php', $this->tmp);

        self::assertSame(0, $result['code'], $result['out']);
        self::assertStringContainsString('0 خطا', $result['out']);
    }

    public function testLintPhpDetectsSyntaxError(): void
    {
        \file_put_contents($this->tmp . '/broken.php', "<?php\n\nfunction broken( {\n");

        $result = $this->run('lint:php', $this->tmp);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('broken.php', $result['out']);
    }

    public function testLintPhpJsonOutputIsMachineReadable(): void
    {
        \file_put_contents($this->tmp . '/broken.php', "<?php\n\nfunction broken( {\n");

        $result = $this->run('lint:php', $this->tmp, '--json');

        $decoded = \json_decode($result['out'], true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('ok', $decoded);
        self::assertArrayHasKey('failures', $decoded);
        self::assertFalse((bool) $decoded['ok']);
        self::assertGreaterThan(0, \count((array) $decoded['failures']));
    }

    // ─── security:scan ─────────────────────────────────────────────────────

    public function testSecurityScanDetectsEvalInGivenPath(): void
    {
        \file_put_contents($this->tmp . '/evil.php', "<?php\n\neval(\$_POST['x']);\n");

        $result = $this->run('security:scan', $this->tmp);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('eval', $result['out']);
    }

    public function testSecurityScanCleanPathPasses(): void
    {
        \file_put_contents($this->tmp . '/safe.php', "<?php\n\nfinal class Safe {}\n");

        $result = $this->run('security:scan', $this->tmp);

        self::assertSame(0, $result['code'], $result['out']);
    }

    public function testSecurityScanJsonStructure(): void
    {
        \file_put_contents($this->tmp . '/evil.php', "<?php\n\neval('x');\n");

        $result = $this->run('security:scan', $this->tmp, '--json');

        $decoded = \json_decode($result['out'], true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('findings', $decoded);
    }

    // ─── env:check / doctor / list ─────────────────────────────────────────

    public function testEnvCheckReportsDrift(): void
    {
        $result = $this->run('env:check');

        // exit ممکن است 0 (بدون drift) یا 1 (drift) باشد — خروجی باید گزارش باشد
        self::assertTrue(\in_array($result['code'], [0, 1], true), $result['out']);
        self::assertNotSame('', \trim($result['out']));

        $json = $this->run('env:check', '--json');
        $decoded = \json_decode($json['out'], true);
        self::assertIsArray($decoded);
    }

    public function testDoctorRunsGreen(): void
    {
        $result = $this->run('doctor');

        self::assertSame(0, $result['code'], $result['out']);
        self::assertNotSame('', \trim($result['out']));
    }

    public function testListCommandShowsRegistry(): void
    {
        $result = $this->run('list');

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('test', $result['out']);
        self::assertStringContainsString('migrate', $result['out']);
    }

    // ─── validateها (manifest) ─────────────────────────────────────────────

    public function testModuleValidatePassesOnRepo(): void
    {
        $result = $this->run('module:validate');

        self::assertSame(0, $result['code'], \substr($result['out'], -400));
        self::assertStringContainsString('معتبر', $result['out']);
    }

    public function testEngineValidatePassesOnRepo(): void
    {
        $result = $this->run('engine:validate');

        self::assertSame(0, $result['code'], \substr($result['out'], -400));
    }

    // ─── down/up (حالت تعمیر — چرخهٔ کامل با پاک‌سازی) ────────────────────

    public function testDownUpCycle(): void
    {
        $downFile = TVN_BASE_PATH . '/storage/framework/down';
        self::assertFileDoesNotExist($downFile);

        $down = $this->run('down', '--message=تعمیرات', '--retry=60');
        self::assertSame(0, $down['code'], $down['out']);
        self::assertFileExists($downFile);

        $meta = \json_decode((string) \file_get_contents($downFile), true);
        self::assertIsArray($meta);
        self::assertSame('تعمیرات', $meta['message'] ?? '');

        $up = $this->run('up');
        self::assertSame(0, $up['code'], $up['out']);
        self::assertFileDoesNotExist($downFile);
    }

    // ─── route/event/kit (read-only) ───────────────────────────────────────

    public function testRouteListContainsKnownRoutes(): void
    {
        $result = $this->run('route:list', '--path=/time');

        self::assertSame(0, $result['code'], $result['out']);
        self::assertStringContainsString('/time', $result['out']);
    }

    public function testRouteMatchResolvesHandler(): void
    {
        $result = $this->run('route:match', 'GET', '/time');

        self::assertSame(0, $result['code'], $result['out']);
        self::assertStringContainsString('time', $result['out']);
    }

    public function testEventListShowsRegisteredListeners(): void
    {
        $result = $this->run('event:list');

        self::assertSame(0, $result['code'], $result['out']);
        // رویدادهای ماژول bpms (providerها در forConsole boot می‌شوند)
        self::assertStringContainsString('bpms.task.created', $result['out']);
    }

    public function testKitListShowsAllEightKits(): void
    {
        $result = $this->run('kit:list');

        self::assertSame(0, $result['code'], $result['out']);
        foreach (['blog', 'news', 'shop', 'forum', 'lms', 'crm', 'erp', 'bpms'] as $kit) {
            self::assertStringContainsString($kit, $result['out']);
        }
    }

    // ─── ابزار ─────────────────────────────────────────────────────────────

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            \is_dir($path) ? $this->rrmdir($path) : @\unlink($path);
        }
        @\rmdir($dir);
    }
}
