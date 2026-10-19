<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Upgrade\Exceptions\UpgradeException;
use Tavaan\Engine\Upgrade\Support\BackupRunnerInterface;
use Tavaan\Engine\Upgrade\Upgrader;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\Mock\Mock;
use Tavaan\Testing\TestCase;

/**
 * تست جریان ارتقا — DOC-081E §9 (T-01/T-04/T-06) + §2.
 *
 * BackupRunner با Mock موتور Test جایگزین شده (بدون I/O واقعی backup) و
 * patchها در tmp ساخته می‌شوند. DB = SQLite in-memory.
 */
final class UpgraderFlowTest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private string $tmp = '';

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $this->tmp = \sys_get_temp_dir() . '/tvn-upgrader-flow-' . \getmypid();
        $this->clean($this->tmp);
        \mkdir($this->tmp . '/upgrades', 0775, true);
        \file_put_contents($this->tmp . '/.env', "TVN_OLD=1\n");
    }

    protected function tearDown(): void
    {
        $this->clean($this->tmp);
    }

    // ─── T-01: جریان کامل ارتقا ──────────────────────────────────────────

    public function testUpgrade_RunsPathStepwise_AppliesPatchesAndConfig(): void
    {
        $this->makePatch('1.1.0', 'patch_001_a.php', 'a');
        $this->makePatch('1.2.0', 'patch_001_b.php', 'b');
        \file_put_contents($this->tmp . '/upgrades/1.2.0/config.php', <<<'PHP'
        <?php
        return [
            ['type' => 'rename', 'from' => 'TVN_OLD', 'to' => 'TVN_NEW'],
        ];
        PHP);

        $backup = $this->backupMock(runs: 1);
        $upgrader = $this->upgrader($backup);
        $upgrader->repository()->setMeta('current_version', '1.0.0');

        $result = $upgrader->upgrade('1.2.0');

        self::assertSame('1.2.0', $result);
        self::assertSame('1.2.0', $upgrader->currentVersion());

        // هر دو patch دقیقاً یک‌بار اجرا شدند
        self::assertSame('1', $this->counter('a'));
        self::assertSame('1', $this->counter('b'));

        // config migration نسخهٔ 1.2.0 روی .env اعمال شد
        $env = (string) \file_get_contents($this->tmp . '/.env');
        self::assertStringContainsString('TVN_NEW=1', $env);
        self::assertStringNotContainsString('TVN_OLD', $env);

        // maintenance پس از پایان برداشته شد (T-06)
        self::assertFalse($upgrader->isDown());
    }

    public function testUpgrade_SecondRun_SameVersion_Rejected(): void
    {
        $this->makePatch('1.1.0', 'patch_001_a.php', 'a');
        $upgrader = $this->upgrader($this->backupMock(runs: 1));
        $upgrader->repository()->setMeta('current_version', '1.0.0');

        $upgrader->upgrade('1.1.0');

        $this->expectException(UpgradeException::class);
        $this->expectExceptionMessageId('upgrade.no_path');
        $upgrader->upgrade('1.1.0');   // هم‌نسخه → مسیر ارتقا نیست
    }

    public function testUpgrade_RerunAfterInterrupt_SkipsAppliedPatches(): void
    {
        // T-02 در سطح جریان: patchهای ثبت‌شده دوباره اجرا نمی‌شوند
        $this->makePatch('1.1.0', 'patch_001_a.php', 'a');
        $this->makePatch('1.2.0', 'patch_001_b.php', 'b');

        $first = $this->upgrader($this->backupMock(runs: 1));
        $first->repository()->setMeta('current_version', '1.0.0');
        $first->upgrade('1.1.0');

        // «فرایند تازه»: Upgrader جدید روی همان repository
        $second = $this->upgrader($this->backupMock(runs: 1));
        $second->upgrade('1.2.0');

        self::assertSame('1', $this->counter('a'));   // یک‌بار (گام اول)
        self::assertSame('1', $this->counter('b'));   // یک‌بار (گام دوم)
        self::assertSame('1.2.0', $second->currentVersion());
    }

    // ─── T-05: pre-check ─────────────────────────────────────────────────

    public function testUpgrade_PrecheckFailure_BlocksEverything(): void
    {
        $this->makePatch('1.1.0', 'patch_001_a.php', 'a');
        $backup = $this->backupMock(runs: 0);   // backup هم نباید گرفته شود

        $upgrader = $this->upgrader($backup, config: ['precheck' => ['php_min' => '99.0.0']]);
        $upgrader->repository()->setMeta('current_version', '1.0.0');

        try {
            $upgrader->upgrade('1.1.0');
            self::fail('باید precheck_failed می‌داد');
        } catch (UpgradeException $e) {
            self::assertSame('upgrade.precheck_failed', $e->messageId);
        }

        self::assertSame('', $this->counter('a'));   // هیچ patch اجرا نشد
        self::assertSame('1.0.0', $upgrader->currentVersion());
    }

    // ─── backup اجباری (§8) ──────────────────────────────────────────────

    public function testUpgrade_RequireBackup_NoBackupFlag_Rejected(): void
    {
        $upgrader = $this->upgrader($this->backupMock(runs: 0));
        $upgrader->repository()->setMeta('current_version', '1.0.0');

        $this->expectException(UpgradeException::class);
        $this->expectExceptionMessageId('upgrade.backup_failed');
        $upgrader->upgrade('1.1.0', ['no_backup' => true]);
    }

    // ─── شکست میانی → rollback خودکار ───────────────────────────────────

    public function testUpgrade_FailingPatch_TriggersAutoRollback(): void
    {
        $this->makePatch('1.1.0', 'patch_001_a.php', 'a');
        $dir = $this->tmp . '/upgrades/1.2.0';
        \mkdir($dir, 0775, true);
        \file_put_contents($dir . '/patch_001_boom.php', <<<'PHP'
        <?php
        return new class extends \Tavaan\Engine\Upgrade\DataPatch {
            public string $version = '1.2.0';
            public function up(): void { throw new \RuntimeException('boom'); }
        };
        PHP);

        $backup = $this->backupMock(runs: 1, restores: 1);   // restore باید دقیقاً یک‌بار صدا زده شود
        $upgrader = $this->upgrader($backup);
        $upgrader->repository()->setMeta('current_version', '1.0.0');

        try {
            $upgrader->upgrade('1.2.0');
            self::fail('باید patch_failed می‌داد');
        } catch (UpgradeException $e) {
            self::assertSame('upgrade.patch_failed', $e->messageId);
        }

        // نسخه به حالت قبل برگشت و maintenance برداشته شد
        self::assertSame('1.0.0', $upgrader->currentVersion());
        self::assertFalse($upgrader->isDown());
    }

    // ─── T-04: rollback دستی ─────────────────────────────────────────────

    public function testRollback_RestoresLastBackupAndVersion(): void
    {
        $this->makePatch('1.1.0', 'patch_001_a.php', 'a');
        $backup = $this->backupMock(runs: 1, restores: 1);
        $upgrader = $this->upgrader($backup);
        $upgrader->repository()->setMeta('current_version', '1.0.0');

        $upgrader->upgrade('1.1.0');
        self::assertSame('1.1.0', $upgrader->currentVersion());

        $rolled = $upgrader->rollback();

        self::assertSame('1.0.0', $rolled);
        self::assertSame('1.0.0', $upgrader->currentVersion());
    }

    public function testRollback_WithoutBackup_Throws(): void
    {
        $upgrader = $this->upgrader($this->backupMock(runs: 0));

        $this->expectException(UpgradeException::class);
        $this->expectExceptionMessageId('upgrade.no_backup');
        $upgrader->rollback();
    }

    // ─── T-06: maintenance mode ──────────────────────────────────────────

    public function testMaintenance_DownAndUpToggleFile(): void
    {
        $upgrader = $this->upgrader($this->backupMock(runs: 0));

        self::assertFalse($upgrader->isDown());
        $upgrader->down();
        self::assertTrue($upgrader->isDown());
        self::assertFileExistsReadable($this->tmp . '/down');
        $upgrader->up();
        self::assertFalse($upgrader->isDown());
    }

    // ─── status / patches / check ────────────────────────────────────────

    public function testStatusAndPatches_Report(): void
    {
        $this->makePatch('1.1.0', 'patch_001_a.php', 'a');
        $this->makePatch('1.2.0', 'patch_001_b.php', 'b');

        $upgrader = $this->upgrader($this->backupMock(runs: 0));
        $upgrader->repository()->setMeta('current_version', '1.0.0');

        $status = $upgrader->status('1.2.0');
        self::assertSame('1.0.0', $status['current']);
        self::assertSame(['1.1.0', '1.2.0'], $status['available']);
        self::assertSame('1.1.0', $status['next']);
        self::assertSame(['1.1.0', '1.2.0'], $status['path']);

        $patches = $upgrader->patches();
        self::assertCount(2, $patches);
        self::assertFalse($patches[0]['applied']);

        $check = $upgrader->check('1.2.0');
        self::assertTrue($check['ok']);
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    /**
     * Mock موتور Test برای BackupRunner (dogfooding M8/TVN-0084).
     */
    private function backupMock(int $runs = 0, int $restores = 0): BackupRunnerInterface
    {
        $builder = Mock::of(BackupRunnerInterface::class);
        if ($runs > 0) {
            $expectation = $builder->shouldReceive('run')->andReturn('backup-test-1');
            if ($runs === 1) {
                $expectation->once();
            }
        }
        if ($restores > 0) {
            $builder->shouldReceive('restore')->times($restores);
        }

        $mock = $builder->getMock();
        self::assertInstanceOf(BackupRunnerInterface::class, $mock);

        return $mock;
    }

    /** @param array<string, mixed> $config */
    private function upgrader(BackupRunnerInterface $backup, array $config = []): Upgrader
    {
        return new Upgrader($this->app(), [
            'path' => $this->tmp . '/upgrades',
            'precheck' => ['php_min' => '8.2.0', 'extensions' => ['json'], 'min_free_mb' => 0],
            'require_backup' => true,
            'schema_migrations' => false,   // تمرکز تست روی patchها (migration موتور M3 جدا تست شده)
            'maintenance' => ['enabled' => true, 'message' => 'testing'],
            'down_file' => $this->tmp . '/down',
            ...$config,
        ], $backup);
    }

    /** دسترسی مستقیم به meta برای تعیین نسخهٔ شروع (شبیه‌سازی نصب موجود). */
    private function makePatch(string $version, string $name, string $counterKey): void
    {
        $dir = $this->tmp . '/upgrades/' . $version;
        \mkdir($dir, 0775, true);
        $file = $this->counterFile($counterKey);
        \file_put_contents($dir . '/' . $name, <<<PHP
        <?php
        return new class extends \\Tavaan\\Engine\\Upgrade\\DataPatch {
            public string \$version = '{$version}';
            public function up(): void
            {
                \$file = '{$file}';
                \$current = (int) @\\file_get_contents(\$file);
                \\file_put_contents(\$file, (string) (\$current + 1));
            }
        };
        PHP);
    }

    private function counterFile(string $key): string
    {
        return $this->tmp . '/counter-' . $key . '.txt';
    }

    private function counter(string $key): string
    {
        $value = @\file_get_contents($this->counterFile($key));

        return $value === false ? '' : $value;
    }

    private function assertFileExistsReadable(string $path): void
    {
        self::assertTrue(\is_file($path), 'فایل وجود ندارد: ' . $path);
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
