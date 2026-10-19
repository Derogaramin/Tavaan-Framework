<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\Hook;
use Tavaan\Engine\Plugin\Plugin;
use Tavaan\Engine\Plugin\PluginCapabilities;
use Tavaan\Engine\Plugin\PluginManager;
use Tavaan\Engine\Plugin\PluginProvider;
use Tavaan\Engine\Plugin\PluginVerifier;
use Tavaan\Support\Str;
use Tavaan\Testing\TestCase;

/**
 * تست چرخهٔ عمر افزونه — DOC-079 §10 (T-01..T-08) + AC-PLG-09.
 *
 * Fixtureها در زمان تست ساخته می‌شوند: کلید Ed25519 با sodium تولید و
 * manifest با `PluginVerifier::canonicalPayload()` امضا می‌شود (مسیر واقعی
 * ابزار امضای فروشنده). همه‌چیز در sys_get_temp_dir — بدون I/O روی مخزن.
 */
final class PluginLifecycleTest extends TestCase
{
    private string $tmp = '';

    private Application $app;

    private string $publicKey = '';

    private string $secretKey = '';

    protected function setUp(): void
    {
        if (!\extension_loaded('sodium')) {
            self::fail('PHP sodium برای تست امضای افزونه لازم است.');
        }

        $this->tmp = \sys_get_temp_dir() . '/tvn-plugin-test-' . \getmypid();
        $this->clean($this->tmp);
        \mkdir($this->tmp . '/source', 0775, true);
        \mkdir($this->tmp . '/plugins', 0775, true);

        Hook::flush();
        PluginCapabilities::flush();
        PluginProvider::clearPluginIds();

        $this->app = \tavaan_test_app();

        $keypair = \sodium_crypto_sign_keypair();
        $this->secretKey = \sodium_crypto_sign_secretkey($keypair);
        $this->publicKey = \base64_encode(\sodium_crypto_sign_publickey($keypair));
    }

    protected function tearDown(): void
    {
        Hook::flush();
        PluginCapabilities::flush();
        PluginProvider::clearPluginIds();
        $this->clean($this->tmp);
    }

    // ─── T-01: امضا ───────────────────────────────────────────────────────

    public function testInstall_ValidSignature_SucceedsAndLoads(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $manager = $this->manager();
        $plugin = $manager->install($source);

        self::assertSame('acme.demo', $plugin->id);
        self::assertSame('1.0.0', $plugin->version);
        self::assertTrue($plugin->enabled);
        self::assertTrue($manager->isLoaded('acme.demo'));   // loaded = state همان فرایند/manager
        self::assertTrue(\is_file($this->tmp . '/plugins/acme-demo/plugin.json'));

        // provider بوت شده → listener هوک ثبت شده
        Hook::declare('demo.greet');   // برای fire (declare در config allowed_hooks تست هم هست)
        self::assertTrue(Hook::has('demo.greet'));
    }

    public function testInstall_TamperedManifest_SignatureInvalid(): void
    {
        $source = $this->buildPluginSource('acme.demo');

        // دستکاری پس از امضا: version عوض می‌شود ولی signature نه
        $manifest = $this->readManifest($source);
        $manifest['version'] = '9.9.9';
        \file_put_contents($source . '/plugin.json', (string) \json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.signature_invalid');
        $this->manager()->install($source);
    }

    public function testInstall_MissingSignature_Rejected_WhenRequired(): void
    {
        // AC-PLG-09: بدون امضا + require_signature=true → نصب نمی‌شود
        $source = $this->buildPluginSource('acme.demo', sign: false);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.signature_missing');
        $this->manager()->install($source);
    }

    public function testInstall_MissingSignature_Allowed_WhenNotRequired(): void
    {
        $source = $this->buildPluginSource('acme.demo', sign: false);
        $plugin = $this->manager(['require_signature' => false])->install($source);

        self::assertSame('acme.demo', $plugin->id);
    }

    public function testInstall_UntrustedVendor_Rejected(): void
    {
        $source = $this->buildPluginSource('acme.demo', overrides: ['author' => ['name' => 'EvilCorp', 'email' => 'x@evil.example']]);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.vendor_untrusted');
        $this->manager()->install($source);
    }

    // ─── T-02: lock hash → تشخیص دستکاری ─────────────────────────────────

    public function testVerify_DetectsModifiedFile(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $manager = $this->manager();
        $manager->install($source);

        $installed = $this->tmp . '/plugins/acme-demo/src/Greeter.php';
        \file_put_contents($installed, "<?php\n// tampered\n");

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.lock_tampered');
        $manager->verify('acme.demo');
    }

    public function testVerify_DetectsAddedFile(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $manager = $this->manager();
        $manager->install($source);

        \file_put_contents($this->tmp . '/plugins/acme-demo/src/Backdoor.php', "<?php\n// new file\n");

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.lock_tampered');
        $manager->verify('acme.demo');
    }

    public function testVerify_CleanInstall_Passes(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $manager = $this->manager();
        $manager->install($source);

        $manager->verify('acme.demo');   // بدون استثنا
        self::assertTrue(true);
    }

    // ─── T-03: scan در نصب ────────────────────────────────────────────────

    public function testInstall_DangerousCode_BlockedWithoutForce(): void
    {
        $source = $this->buildPluginSource('acme.demo', extraPhp: "<?php\nnamespace Acme\\Demo;\nfinal class Bad { public function x(): void { exec('ls'); } }\n");

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.dangerous_code');
        $this->manager()->install($source);
    }

    public function testInstall_DangerousCode_AllowedWithExplicitForce(): void
    {
        $source = $this->buildPluginSource('acme.demo', extraPhp: "<?php\nnamespace Acme\\Demo;\nfinal class Bad { public function x(): void { exec('ls'); } }\n");

        $plugin = $this->manager()->install($source, force: true);   // تأیید صریح admin
        self::assertSame('acme.demo', $plugin->id);
    }

    // ─── T-04: capability enforcement ────────────────────────────────────

    public function testInstall_DatabaseCapability_DeniedByPolicy(): void
    {
        $source = $this->buildPluginSource('acme.demo', overrides: [
            'capabilities' => ['network' => [], 'filesystem' => [], 'database' => true, 'shell' => false],
        ]);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.capability_denied');
        $this->manager()->install($source);
    }

    public function testInstall_ShellCapability_DeniedByPolicy(): void
    {
        $source = $this->buildPluginSource('acme.demo', overrides: [
            'capabilities' => ['network' => [], 'filesystem' => [], 'database' => false, 'shell' => true],
        ]);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.capability_denied');
        $this->manager()->install($source);
    }

    public function testInstall_NetworkWildcard_Denied(): void
    {
        $source = $this->buildPluginSource('acme.demo', overrides: [
            'capabilities' => ['network' => ['*'], 'filesystem' => [], 'database' => false, 'shell' => false],
        ]);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.capability_denied');
        $this->manager()->install($source);
    }

    public function testInstall_FilescapeTraversal_Denied(): void
    {
        $source = $this->buildPluginSource('acme.demo', overrides: [
            'capabilities' => ['network' => [], 'filesystem' => ['read:../etc'], 'database' => false, 'shell' => false],
        ]);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.capability_denied');
        $this->manager()->install($source);
    }

    public function testCapabilities_RegisteredForRuntimeConsultation(): void
    {
        $source = $this->buildPluginSource('acme.demo', overrides: [
            'capabilities' => ['network' => ['api.example.com'], 'filesystem' => [], 'database' => false, 'shell' => false],
        ]);
        $this->manager()->install($source);

        $caps = PluginCapabilities::forClass('Acme\\Demo\\Greeter');
        self::assertNotNull($caps);
        self::assertSame(['api.example.com'], $caps['network']);

        // SSRF از پلاگین → دامنهٔ خارج از allowlist رد می‌شود (DOC-079 §9)
        PluginCapabilities::assertNetwork('Acme\\Demo\\Greeter', 'https://api.example.com/v1');
        $denied = false;
        try {
            PluginCapabilities::assertNetwork('Acme\\Demo\\Greeter', 'https://evil.example/x');
        } catch (PluginException) {
            $denied = true;
        }
        self::assertTrue($denied);

        // کد میزبان بدون محدودیت افزونه
        PluginCapabilities::assertNetwork('Tavaan\\Support\\Str', 'https://anything.example/x');
    }

    // ─── T-05: hook listener ──────────────────────────────────────────────

    public function testHook_PluginListener_ReceivesFire(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $this->manager()->install($source);

        Hook::declare('demo.greet');
        $results = Hook::fire('demo.greet', 'tavaan');

        self::assertCount(1, $results);
        self::assertStringContainsString('pong:tavaan', (string) $results[0]);
    }

    public function testHook_PluginCannotListenToUndeclaredHook(): void
    {
        // فضای‌نام/id متفاوت: کلاس PHP در فرایند unload نمی‌شود و fixture
        // استاندارد ممکن است پیش‌تر load شده باشد
        $source = $this->buildPluginSource('acme.bad-hook', ns: 'Acme\\BadHook', subdir: 'acme-bad-hook', providerBody: <<<'PHP'
            public function boot(): void
            {
                $this->listenHook('host.private.hook', static fn (): string => 'x');
            }
        PHP);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.hook_not_declared');
        $this->manager()->install($source);
    }

    // ─── T-06: install/enable/disable ─────────────────────────────────────

    public function testLifecycle_DisableEnableUninstall(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $manager = $this->manager();
        $manager->install($source);
        Hook::declare('demo.greet');

        // disable → hookها قطع، lock به‌روز
        $manager->disable('acme.demo');
        self::assertFalse(Hook::has('demo.greet'));
        self::assertFalse($manager->isLoaded('acme.demo'));
        self::assertFalse((bool) ($manager->lock()->entry('acme.demo')['enabled'] ?? true));

        // enable مجدد → provider دوباره boot و hook برمی‌گردد
        $manager->enable('acme.demo');
        self::assertTrue($manager->isLoaded('acme.demo'));
        self::assertStringContainsString('pong:', (string) (Hook::fire('demo.greet', 'again')[0] ?? ''));

        // uninstall با purge → فایل‌ها و lock پاک
        $manager->uninstall('acme.demo', deleteFiles: true);
        self::assertTrue(!\is_dir($this->tmp . '/plugins/acme-demo'));
        self::assertFalse($manager->lock()->has('acme.demo'));
        self::assertFalse(Hook::has('demo.greet'));
    }

    public function testInstall_Twice_Rejected(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $manager = $this->manager();
        $manager->install($source);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.already_installed');
        $manager->install($source);
    }

    public function testUpdate_BackupsAndRelocks(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $manager = $this->manager();
        $manager->install($source);

        $updated = $this->buildPluginSource('acme.demo', overrides: ['version' => '1.1.0'], subdir: 'acme-demo-v2');
        $plugin = $manager->update('acme.demo', $updated);

        self::assertSame('1.1.0', $plugin->version);
        $manager->verify('acme.demo');   // hashهای جدید با lock می‌خواند

        $backups = \glob($this->tmp . '/backups/acme-demo-*', GLOB_ONLYDIR) ?: [];
        self::assertGreaterThan(0, \count($backups), 'پشتیبان به‌روزرسانی ساخته نشد');
    }

    public function testLoadEnabled_FreshManager_ReloadsPlugins(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $this->manager()->install($source);
        Hook::flush();   // شبیه‌سازی فرایند تازه

        $fresh = $this->manager();
        $fresh->loadEnabled();

        self::assertTrue($fresh->isLoaded('acme.demo'));
        Hook::declare('demo.greet');
        self::assertStringContainsString('pong:', (string) (Hook::fire('demo.greet', 'rebooted')[0] ?? ''));
    }

    // ─── T-07/T-08: vendor ایزوله + وابستگی composer ─────────────────────

    public function testVendor_IsolatedFromCore_AndUsableByPlugin(): void
    {
        // vendor افزونه: کلاس VendorPkg\Marker + تلاش shadow برای Tavaan\Support\Str
        $source = $this->buildPluginSource('acme.demo', withVendor: true);
        $this->manager()->install($source);

        Hook::declare('demo.greet');
        $result = (string) (Hook::fire('demo.greet', 'x')[0] ?? '');

        // T-08: وابستگی composer پلاگین کار می‌کند
        self::assertStringContainsString('vendor-ok', $result);

        // T-07: هسته shadow نشد — Str هسته اولویت دارد (loader vendor append شده)
        self::assertSame('Products', Str::plural('Product'));
    }

    // ─── manifest / سازگاری نسخه ─────────────────────────────────────────

    public function testInstall_FrameworkConstraint_Enforced(): void
    {
        $source = $this->buildPluginSource('acme.demo', overrides: ['tavaan' => '>=99.0 <100.0']);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.manifest_invalid');
        $this->manager()->install($source);
    }

    public function testList_ShowsInstalledPlugins(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $this->manager()->install($source);

        $list = $this->manager()->list();
        self::assertCount(1, $list);
        self::assertSame('acme.demo', $list[0]->id);
        self::assertTrue($list[0]->enabled);
        self::assertTrue($list[0]->installed);
    }

    public function testPluginVo_ExposesManifestData(): void
    {
        $source = $this->buildPluginSource('acme.demo');
        $plugin = Plugin::fromDirectory($source);

        self::assertSame('acme-demo', $plugin->slug());
        self::assertSame('Acme\\Demo\\', $plugin->namespace());
        self::assertSame('Acme\\Demo\\ServiceProvider', $plugin->provider);
        self::assertSame(['demo.greet'], $plugin->hooks);
        self::assertSame(['demo.greet'], $plugin->permissions);
        self::assertFalse($plugin->hasVendor());
        self::assertTrue($plugin->supportsFramework('1.0.0'));
        self::assertFalse($plugin->supportsFramework('3.0.0'));
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $configOverrides */
    private function manager(array $configOverrides = []): PluginManager
    {
        $config = [
            'path' => $this->tmp . '/plugins',
            'enabled' => true,
            'require_signature' => true,
            'trusted_vendors' => ['TestVendor' => 'ed25519:base64:' . $this->publicKey],
            'allow_vendor' => true,
            'allowed_hooks' => ['demo.greet'],
            'sandbox' => ['database' => false, 'shell' => false, 'network_allowlist' => true],
            'lock_file' => $this->tmp . '/plugins/plugins.lock',
            'backup_path' => $this->tmp . '/backups',
            'hook_timeout_ms' => 5000,
            'hook_memory_limit' => null,
            ...$configOverrides,
        ];

        return new PluginManager($this->app, $config);
    }

    /**
     * ساخت دایرکتوری منبع افزونهٔ fixture (با امضای واقعی Ed25519).
     *
     * @param array<string, mixed> $overrides
     */
    private function buildPluginSource(
        string $id,
        bool $sign = true,
        array $overrides = [],
        ?string $extraPhp = null,
        bool $withVendor = false,
        ?string $providerBody = null,
        string $subdir = 'acme-demo',
        string $ns = 'Acme\\Demo',
    ): string {
        $dir = $this->tmp . '/source/' . $subdir;
        \mkdir($dir . '/src', 0775, true);

        $manifest = [
            'id' => $id,
            'name' => 'Demo Plugin',
            'version' => '1.0.0',
            'author' => ['name' => 'TestVendor', 'email' => 'dev@test.local', 'url' => 'https://test.local'],
            'license' => 'MIT',
            'tavaan' => '>=0.1 <3.0',
            'requires' => ['php' => '>=8.2'],
            'autoload' => ['psr-4' => [$ns . '\\' => 'src/']],
            'provider' => $ns . '\\ServiceProvider',
            'permissions' => ['demo.greet'],
            'hooks' => ['demo.greet'],
            'capabilities' => ['network' => [], 'filesystem' => [], 'database' => false, 'shell' => false],
            ...$overrides,
        ];

        if ($sign) {
            $payload = PluginVerifier::canonicalPayload($manifest);
            $signature = \sodium_crypto_sign_detached($payload, $this->secretKey);
            $manifest['signature'] = 'ed25519:base64:' . \base64_encode($signature);
        }

        \file_put_contents(
            $dir . '/plugin.json',
            (string) \json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        $boot = $providerBody ?? <<<'PHP'
            public function boot(): void
            {
                $this->listenHook('demo.greet', static fn (string $who): string => 'pong:' . $who . ':' . Greeter::hello());
            }
        PHP;

        \file_put_contents($dir . '/src/ServiceProvider.php', <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns};

        use Tavaan\\Engine\\Plugin\\PluginProvider;

        final class ServiceProvider extends PluginProvider
        {
        {$boot}
        }

        PHP);

        \file_put_contents($dir . '/src/Greeter.php', <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns};

        final class Greeter
        {
            public static function hello(): string
            {
                return \class_exists(\VendorPkg\Marker::class) ? \VendorPkg\Marker::tag() : 'no-vendor';
            }
        }

        PHP);

        if ($extraPhp !== null) {
            \file_put_contents($dir . '/src/Bad.php', $extraPhp);
        }

        if ($withVendor) {
            \mkdir($dir . '/vendor/vendorpkg', 0775, true);
            \mkdir($dir . '/vendor/shadow', 0775, true);

            \file_put_contents($dir . '/vendor/autoload.php', <<<'PHP'
            <?php
            // شبیه‌ساز composer autoload افزونه (معافیت صفر‌وابستگی — DOC-079 §1)
            \spl_autoload_register(static function (string $class): void {
                if (\str_starts_with($class, 'VendorPkg\\')) {
                    $relative = \str_replace('\\', '/', \substr($class, \strlen('VendorPkg\\')));
                    $file = __DIR__ . '/vendorpkg/' . $relative . '.php';
                    if (\is_file($file)) {
                        require $file;
                    }
                    return;
                }
                if ($class === 'Tavaan\\Support\\Str' && !\class_exists('Tavaan\\Support\\Str', false)) {
                    require __DIR__ . '/shadow/Str.php';   // تلاش shadow — نباید هرگز برنده شود
                }
            }, throw: true, prepend: false);

            PHP);

            \file_put_contents($dir . '/vendor/vendorpkg/Marker.php', <<<'PHP'
            <?php
            namespace VendorPkg;
            final class Marker { public static function tag(): string { return 'vendor-ok'; } }

            PHP);

            \file_put_contents($dir . '/vendor/shadow/Str.php', <<<'PHP'
            <?php
            namespace Tavaan\Support;
            final class Str { public static function plural(string $v): string { return 'HIJACKED'; } }

            PHP);
        }

        return $dir;
    }

    /** @return array<string, mixed> */
    private function readManifest(string $dir): array
    {
        $decoded = \json_decode((string) \file_get_contents($dir . '/plugin.json'), true);

        return \is_array($decoded) ? $decoded : [];
    }

    private function frameworkRoot(): string
    {
        return \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname(__DIR__, 4);
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
