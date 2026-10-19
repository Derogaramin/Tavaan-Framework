<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Tests\Unit;

use Tavaan\Engine\Upgrade\ConfigMigrator;
use Tavaan\Engine\Upgrade\DataPatch;
use Tavaan\Engine\Upgrade\Exceptions\UpgradeException;
use Tavaan\Engine\Upgrade\PatchRunner;
use Tavaan\Engine\Upgrade\PreChecker;
use Tavaan\Engine\Upgrade\UpgradeRepository;
use Tavaan\Engine\Upgrade\VersionResolver;
use Tavaan\Testing\TestCase;

/**
 * تست واحد اجزای Upgrade — DOC-081E §9 (T-01/T-02/T-03/T-05).
 */
final class UpgradeUnitTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-upgrade-test-' . \getmypid();
        $this->clean($this->tmp);
        \mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->clean($this->tmp);
    }

    // ─── T-01: VersionResolver ────────────────────────────────────────────

    public function testUpgradePath_IsStepwise(): void
    {
        $this->makeVersionDirs(['1.0.1', '1.1.0', '1.2.0', '2.0.0']);
        $resolver = new VersionResolver($this->tmp . '/upgrades');

        self::assertSame(['1.0.1', '1.1.0', '1.2.0'], $resolver->upgradePath('1.0.0', '1.2.0'));
        self::assertSame(['1.1.0', '1.2.0'], $resolver->upgradePath('1.0.1', '1.2.0'));
        // نسخهٔ هدف بدون دایرکتوری هم به‌عنوان گام آخر پذیرفته می‌شود (schema-only)
        self::assertSame(['1.1.0', '1.1.5'], $resolver->upgradePath('1.0.1', '1.1.5'));
    }

    public function testUpgradePath_NoDowngrade(): void
    {
        $this->makeVersionDirs(['1.1.0']);
        $resolver = new VersionResolver($this->tmp . '/upgrades');

        $this->expectException(UpgradeException::class);
        $this->expectExceptionMessageId('upgrade.no_path');
        $resolver->upgradePath('1.2.0', '1.1.0');
    }

    public function testUpgradePath_SameVersion_NoPath(): void
    {
        $resolver = new VersionResolver($this->tmp . '/upgrades');

        $this->expectException(UpgradeException::class);
        $resolver->upgradePath('1.0.0', '1.0.0');
    }

    public function testAvailableVersions_SemverSorted_NonSemverIgnored(): void
    {
        $this->makeVersionDirs(['1.10.0', '1.9.0', 'not-a-version']);
        $resolver = new VersionResolver($this->tmp . '/upgrades');

        self::assertSame(['1.9.0', '1.10.0'], $resolver->availableVersions());
        self::assertSame('1.9.0', $resolver->nextVersion('1.8.0'));
        self::assertNull($resolver->nextVersion('2.0.0'));
    }

    public function testInvalidVersion_Rejected(): void
    {
        $resolver = new VersionResolver($this->tmp . '/upgrades');

        $this->expectException(UpgradeException::class);
        $this->expectExceptionMessageId('upgrade.invalid_version');
        $resolver->upgradePath('v1', '1.0.0');
    }

    // ─── T-03: ConfigMigrator ─────────────────────────────────────────────

    public function testConfigMigrator_RenameRemoveDefaultTransform(): void
    {
        $env = $this->tmp . '/.env';
        \file_put_contents($env, "# comment\nTVN_OLD=value1\nTVN_KEEP=yes\nTVN_NUM=5\n");

        $migrator = new ConfigMigrator($env);
        $log = $migrator->migrate([
            ['type' => 'rename', 'from' => 'TVN_OLD', 'to' => 'TVN_NEW'],
            ['type' => 'remove', 'key' => 'TVN_KEEP'],
            ['type' => 'default', 'key' => 'TVN_ADDED', 'value' => 'on'],
            ['type' => 'default', 'key' => 'TVN_NUM', 'value' => '999'],   // موجود → دست‌نخورده
            ['type' => 'transform', 'key' => 'TVN_NUM', 'callback' => static fn (string $v): string => (string) ((int) $v * 2)],
        ]);

        $contents = (string) \file_get_contents($env);
        self::assertStringContainsString('TVN_NEW=value1', $contents);
        self::assertStringNotContainsString('TVN_OLD', $contents);
        self::assertStringNotContainsString('TVN_KEEP', $contents);
        self::assertStringContainsString('TVN_ADDED=on', $contents);
        self::assertStringContainsString('TVN_NUM=10', $contents);
        self::assertStringContainsString('# comment', $contents);   // کامنت‌ها حفظ می‌شوند
        self::assertCount(4, $log);   // default موجود log نمی‌شود (no-op درست)
    }

    public function testConfigMigrator_MissingFile_NoError(): void
    {
        $migrator = new ConfigMigrator($this->tmp . '/nonexistent.env');
        self::assertSame([], $migrator->migrate([['type' => 'remove', 'key' => 'X']]));
    }

    // ─── T-05: PreChecker ─────────────────────────────────────────────────

    public function testPreChecker_PassesWithSaneRequirements(): void
    {
        $checker = new PreChecker();
        self::assertSame([], $checker->check([
            'php_min' => '8.2.0',
            'extensions' => ['json'],
            'min_free_mb' => 0,
        ]));
    }

    public function testPreChecker_FailsOnImpossiblePhp(): void
    {
        $checker = new PreChecker();
        $problems = $checker->check(['php_min' => '99.0.0', 'extensions' => [], 'min_free_mb' => 0]);

        self::assertCount(1, $problems);
        self::assertStringContainsString('PHP', $problems[0]);
    }

    public function testPreChecker_FailsOnMissingExtension(): void
    {
        $checker = new PreChecker();
        $problems = $checker->check(['php_min' => '8.2.0', 'extensions' => ['definitely_missing_ext'], 'min_free_mb' => 0]);

        self::assertCount(1, $problems);
        self::assertStringContainsString('definitely_missing_ext', $problems[0]);
    }

    // ─── T-02: PatchRunner idempotency ───────────────────────────────────

    public function testPatchRunner_SkipsAppliedPatches(): void
    {
        $counterFile = $this->tmp . '/counter.txt';
        $this->makePatch('1.1.0', 'patch_001_count.php', $counterFile);

        $repository = $this->repository();
        $runner = new PatchRunner($this->tmp . '/upgrades', $repository, $this->tmp . '/.env');

        $first = $runner->run('1.1.0', 1);
        $second = $runner->run('1.1.0', 2);   // اجرای مجدد → skip

        self::assertSame(['patch_001_count.php'], $first);
        self::assertSame([], $second);
        self::assertSame('1', (string) \file_get_contents($counterFile));   // فقط یک‌بار اجرا شد

        $discovered = $runner->discover('1.1.0');
        self::assertTrue($discovered[0]['applied']);
    }

    public function testPatchRunner_VersionMismatch_Rejected(): void
    {
        // patch با version داخلی متفاوت → رد (جلوگیری از جابه‌جایی فایل)
        $dir = $this->tmp . '/upgrades/1.1.0';
        \mkdir($dir, 0775, true);
        \file_put_contents($dir . '/patch_001_bad.php', <<<'PHP'
        <?php
        return new class extends \Tavaan\Engine\Upgrade\DataPatch {
            public string $version = '9.9.9';
            public function up(): void {}
        };
        PHP);

        $runner = new PatchRunner($this->tmp . '/upgrades', $this->repository(), $this->tmp . '/.env');

        $this->expectException(UpgradeException::class);
        $this->expectExceptionMessageId('upgrade.patch_failed');
        $runner->run('1.1.0', 1);
    }

    public function testPatchRunner_FailingPatch_Wrapped(): void
    {
        $dir = $this->tmp . '/upgrades/1.1.0';
        \mkdir($dir, 0775, true);
        \file_put_contents($dir . '/patch_001_fail.php', <<<'PHP'
        <?php
        return new class extends \Tavaan\Engine\Upgrade\DataPatch {
            public string $version = '1.1.0';
            public function up(): void { throw new \RuntimeException('boom'); }
        };
        PHP);

        $runner = new PatchRunner($this->tmp . '/upgrades', $this->repository(), $this->tmp . '/.env');

        $this->expectException(UpgradeException::class);
        $this->expectExceptionMessageId('upgrade.patch_failed');
        $runner->run('1.1.0', 1);
    }

    public function testRepository_MetaAndBatches(): void
    {
        $repository = $this->repository();
        $repository->createRepository();

        self::assertNull($repository->getMeta('x'));
        $repository->setMeta('x', 'y');
        self::assertSame('y', $repository->getMeta('x'));
        $repository->setMeta('x', 'z');
        self::assertSame('z', $repository->getMeta('x'));

        self::assertSame(1, $repository->nextBatch());
        $repository->logPatch('1.0.0', 'patch_001_x.php', 1);
        self::assertSame(2, $repository->nextBatch());
        self::assertTrue($repository->patchApplied('1.0.0', 'patch_001_x.php'));
        self::assertFalse($repository->patchApplied('1.0.0', 'patch_999_y.php'));
        self::assertCount(1, $repository->applied());
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    /** @param list<string> $versions */
    private function makeVersionDirs(array $versions): void
    {
        foreach ($versions as $version) {
            \mkdir($this->tmp . '/upgrades/' . $version, 0775, true);
        }
    }

    private function makePatch(string $version, string $name, string $counterFile): void
    {
        $dir = $this->tmp . '/upgrades/' . $version;
        \mkdir($dir, 0775, true);
        $escaped = \str_replace("'", "\\'", $counterFile);
        \file_put_contents($dir . '/' . $name, <<<PHP
        <?php
        return new class extends \\Tavaan\\Engine\\Upgrade\\DataPatch {
            public string \$version = '{$version}';
            public string \$description = 'counter patch';
            public function up(): void
            {
                \$file = '{$escaped}';
                \$current = (int) @\\file_get_contents(\$file);
                \\file_put_contents(\$file, (string) (\$current + 1));
            }
        };
        PHP);
    }

    private function repository(): UpgradeRepository
    {
        $config = new \Tavaan\Engine\Config\Repository([
            'database' => [
                'default' => 'memory',
                'connections' => ['memory' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']],
            ],
        ]);
        $manager = new \Tavaan\Engine\Database\ConnectionManager($config);

        return new UpgradeRepository($manager->connection(), new \Tavaan\Engine\Database\Schema\SchemaBuilder($manager));
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
