<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module\Tests\Feature;

use Tavaan\Engine\Module\Kit\KitException;
use Tavaan\Engine\Module\Kit\KitManager;
use Tavaan\Testing\TestCase;

/**
 * تست زیرساخت کیت (M9) — KitManager::discover/requirementsCheck/install.
 *
 * همه‌چیز روی base موقت (sys_get_temp_dir) — بدون اثر بر پروژهٔ واقعی.
 * نصب با skip_migrate و roles خالی → بدون لمس DB.
 */
final class KitInstallTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = \sys_get_temp_dir() . '/tvn-kit-test-' . \getmypid();
        $this->rrmdir($this->base);
        $this->makeKit();
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
    }

    private function makeKit(): void
    {
        $kit = $this->base . '/kits/demo-kit';
        @\mkdir($kit . '/presets', 0775, true);
        @\mkdir($kit . '/themes/default/views/layouts', 0775, true);
        @\mkdir($kit . '/themes/default/assets/css', 0775, true);
        @\mkdir($kit . '/seed/demo', 0775, true);
        @\mkdir($this->base . '/storage/framework', 0775, true);
        @\mkdir($this->base . '/config', 0775, true);

        \file_put_contents($kit . '/kit.json', (string) \json_encode([
            'id' => 'tavaan.kit.demo-kit',
            'title_fa' => 'کیت آزمایشی',
            'version' => '1.2.3',
            'architecture' => 'hmvc',
            'modules' => [],
            'theme' => 'themes/default',
            'default_preset' => 'standard',
            'demo_data' => 'seed/demo',
            'requirements' => ['php' => '>=8.2', 'extensions' => ['json']],
        ], JSON_UNESCAPED_UNICODE));

        \file_put_contents($kit . '/presets/standard.json', (string) \json_encode([
            'config' => ['demokit' => ['per_page' => 10, 'nested' => ['a' => 1]]],
        ]));
        \file_put_contents($kit . '/presets/minimal.json', (string) \json_encode([
            'config' => ['demokit' => ['per_page' => 3]],
        ]));

        \file_put_contents($kit . '/seed/roles.json', (string) \json_encode(['roles' => []]));
        \file_put_contents($kit . '/seed/demo/marker.php', '<?php return static function (): void {
            \file_put_contents(\sys_get_temp_dir() . "/tvn-kit-seed-marker-" . \getmypid(), "seeded");
        };');

        \file_put_contents($kit . '/themes/default/views/layouts/main.tv.php', '<html>KIT-THEME</html>');
        \file_put_contents($kit . '/themes/default/assets/css/app.css', '/* kit css */');

        \file_put_contents($this->base . '/tavaan.json', (string) \json_encode(['name' => 'demo', 'kits' => []]));
    }

    private function manager(): KitManager
    {
        return new KitManager($this->appContainer(), $this->base);
    }

    private function appContainer(): \Tavaan\Core\Contracts\Container\ContainerInterface
    {
        // کانتینر سبک بدون ModuleRegistry/Rbac/Migrator — مسیرهای DB-less
        return new \Tavaan\Engine\Container\Container();
    }

    // ─── discover ─────────────────────────────────────────────────────────

    public function testDiscover_BySlugAndFullId(): void
    {
        $kits = $this->manager()->discover();

        self::assertArrayHasKey('demo-kit', $kits);
        self::assertArrayHasKey('tavaan.kit.demo-kit', $kits);
        self::assertSame('1.2.3', $kits['demo-kit']->version);
        self::assertSame('کیت آزمایشی', $kits['demo-kit']->titleFa);
    }

    public function testGet_UnknownKitThrows(): void
    {
        $this->expectException(KitException::class);
        $this->expectExceptionMessageId('kit.not_found');
        $this->manager()->get('no-such-kit');
    }

    public function testPresets_Listed(): void
    {
        $kit = $this->manager()->get('demo-kit');
        self::assertSame(['minimal', 'standard'], $this->manager()->presets($kit));
    }

    // ─── requirements ─────────────────────────────────────────────────────

    public function testRequirementsCheck_Ok(): void
    {
        self::assertSame([], $this->manager()->requirementsCheck($this->manager()->get('demo-kit')));
    }

    public function testRequirementsCheck_ReportsProblems(): void
    {
        $raw = [
            'id' => 'tavaan.kit.heavy',
            'version' => '1.0.0',
            'requirements' => ['php' => '>=99.0', 'extensions' => ['no_such_ext_xyz']],
        ];
        $kit = \Tavaan\Engine\Module\Kit\KitManifest::fromArray($raw, $this->base);

        $problems = $this->manager()->requirementsCheck($kit);
        self::assertCount(2, $problems);
        self::assertStringContainsString('PHP', $problems[0]);
        self::assertStringContainsString('no_such_ext_xyz', $problems[1]);
    }

    // ─── install ──────────────────────────────────────────────────────────

    public function testInstall_FullChain(): void
    {
        $manager = $this->manager();
        $report = $manager->install('demo-kit', ['with_demo' => true, 'skip_migrate' => true]);

        self::assertSame('demo-kit', $report['kit']);
        self::assertSame('standard', $report['preset']);

        // 1) overlay پیکربندی — config/demokit.php (namespace = نام فایل)
        $overlayFile = $this->base . '/config/demokit.php';
        self::assertFileExists($overlayFile);
        $overlay = require $overlayFile;
        self::assertSame(10, $overlay['per_page']);
        self::assertSame(1, $overlay['nested']['a']);

        // 2) registry کیت‌ها — config/kits.php
        $kitsConfig = require $this->base . '/config/kits.php';
        self::assertSame('tavaan.kit.demo-kit', $kitsConfig['demo-kit']['id']);

        // 3) theme کپی شد — vendor override + assets
        self::assertFileExists($this->base . '/resources/views/vendor/demo-kit/layouts/main.tv.php');
        self::assertFileExists($this->base . '/public/assets/kits/demo-kit/css/app.css');

        // 4) demo seed اجرا شد
        self::assertFileExists(\sys_get_temp_dir() . '/tvn-kit-seed-marker-' . \getmypid());
        @\unlink(\sys_get_temp_dir() . '/tvn-kit-seed-marker-' . \getmypid());

        // 5) status + tavaan.json
        $status = require $this->base . '/storage/framework/kits.status.php';
        self::assertSame('1.2.3', $status['demo-kit']['version']);
        self::assertTrue($manager->isInstalled('demo-kit'));

        $project = \json_decode((string) \file_get_contents($this->base . '/tavaan.json'), true);
        self::assertContains('tavaan.kit.demo-kit', $project['kits']);
    }

    public function testInstall_PresetOverrideAndIdempotency(): void
    {
        $manager = $this->manager();
        $manager->install('demo-kit', ['preset' => 'minimal', 'skip_migrate' => true]);

        $overlay = require $this->base . '/config/demokit.php';
        self::assertSame(3, $overlay['per_page']);

        // نصب مجدد (idempotent) — بدون خطا، همان وضعیت
        $report = $manager->install('demo-kit', ['preset' => 'minimal', 'skip_migrate' => true]);
        self::assertSame('minimal', $report['preset']);
    }

    public function testInstall_UnknownPresetThrows(): void
    {
        $this->expectException(KitException::class);
        $this->expectExceptionMessageId('kit.preset_not_found');
        $this->manager()->install('demo-kit', ['preset' => 'no-such-preset', 'skip_migrate' => true]);
    }

    public function testInstall_RequirementsFailureThrows(): void
    {
        $kitDir = $this->base . '/kits/impossible';
        @\mkdir($kitDir, 0775, true);
        \file_put_contents($kitDir . '/kit.json', (string) \json_encode([
            'id' => 'tavaan.kit.impossible',
            'version' => '1.0.0',
            'requirements' => ['php' => '>=99.0'],
        ]));

        $this->expectException(KitException::class);
        $this->expectExceptionMessageId('kit.requirements_not_met');
        $this->manager()->install('impossible', []);
    }

    public function testInvalidManifestRejected(): void
    {
        $kitDir = $this->base . '/kits/broken';
        @\mkdir($kitDir, 0775, true);
        \file_put_contents($kitDir . '/kit.json', '{"id": "BAD ID!", "version": "x"}');

        $this->expectException(KitException::class);
        $this->expectExceptionMessageId('kit.invalid_manifest');
        (new KitManager($this->appContainer(), $this->base))->discover();
    }

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            \is_dir($path) ? $this->rrmdir($path) : @\unlink($path);
        }
        @\rmdir($dir);
    }
}
