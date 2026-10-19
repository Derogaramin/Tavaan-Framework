<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Tests\Unit;

use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\Plugin;
use Tavaan\Engine\Plugin\PluginVerifier;
use Tavaan\Testing\TestCase;

/**
 * تست PluginVerifier — payload کانویک + امضای Ed25519 + capabilities
 * (DOC-079 §4 — T-01/T-04).
 */
final class PluginVerifierTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-verifier-test-' . \getmypid();
        if (!\is_dir($this->tmp)) {
            \mkdir($this->tmp, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
    }

    // ─── canonical payload ────────────────────────────────────────────────

    public function testCanonicalPayload_OrderIndependent(): void
    {
        $a = ['id' => 'x.y', 'version' => '1.0.0', 'author' => ['name' => 'A', 'email' => 'a@b.c']];
        $b = ['author' => ['email' => 'a@b.c', 'name' => 'A'], 'version' => '1.0.0', 'id' => 'x.y'];

        self::assertSame(PluginVerifier::canonicalPayload($a), PluginVerifier::canonicalPayload($b));
    }

    public function testCanonicalPayload_ExcludesSignature(): void
    {
        $base = ['id' => 'x.y', 'version' => '1.0.0'];
        $signed = [...$base, 'signature' => 'ed25519:base64:whatever'];

        self::assertSame(PluginVerifier::canonicalPayload($base), PluginVerifier::canonicalPayload($signed));
        self::assertStringNotContainsString('signature', PluginVerifier::canonicalPayload($signed));
    }

    // ─── امضا ─────────────────────────────────────────────────────────────

    public function testVerifySignature_ValidKeyPair_Passes(): void
    {
        [$plugin, $config] = $this->signedPlugin();
        (new PluginVerifier($config))->verifySignature($plugin);

        self::assertTrue(true);   // بدون استثنا
    }

    public function testVerifySignature_TamperedPayload_Fails(): void
    {
        [$plugin, $config] = $this->signedPlugin(tamper: true);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.signature_invalid');
        (new PluginVerifier($config))->verifySignature($plugin);
    }

    public function testVerifySignature_NoSignature_Required_Throws(): void
    {
        $dir = $this->writePluginDir(['id' => 'x.demo', 'name' => 'X', 'version' => '1.0.0',
            'autoload' => ['psr-4' => ['X\\Demo\\' => 'src/']]]);
        $plugin = Plugin::fromDirectory($dir);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.signature_missing');
        (new PluginVerifier(['require_signature' => true]))->verifySignature($plugin);
    }

    public function testVerifySignature_NoSignature_NotRequired_Passes(): void
    {
        $dir = $this->writePluginDir(['id' => 'x.demo', 'name' => 'X', 'version' => '1.0.0',
            'autoload' => ['psr-4' => ['X\\Demo\\' => 'src/']]]);
        $plugin = Plugin::fromDirectory($dir);

        (new PluginVerifier(['require_signature' => false]))->verifySignature($plugin);
        self::assertTrue(true);
    }

    public function testVerifySignature_MalformedKeySpec_Throws(): void
    {
        [$plugin] = $this->signedPlugin();
        $config = ['require_signature' => true, 'trusted_vendors' => ['TestVendor' => 'rsa:hex:zz']];

        $this->expectException(\InvalidArgumentException::class);
        (new PluginVerifier($config))->verifySignature($plugin);
    }

    // ─── capabilities ─────────────────────────────────────────────────────

    public function testCapabilities_FilesystemFormatValidated(): void
    {
        $dir = $this->writePluginDir([
            'id' => 'x.demo', 'name' => 'X', 'version' => '1.0.0',
            'autoload' => ['psr-4' => ['X\\Demo\\' => 'src/']],
            'capabilities' => ['network' => [], 'filesystem' => ['storage/app'], 'database' => false, 'shell' => false],
        ]);
        $plugin = Plugin::fromDirectory($dir);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.capability_denied');
        (new PluginVerifier())->assertAllowedCapabilities($plugin);
    }

    public function testCapabilities_ValidReadSpec_Passes(): void
    {
        $dir = $this->writePluginDir([
            'id' => 'x.demo', 'name' => 'X', 'version' => '1.0.0',
            'autoload' => ['psr-4' => ['X\\Demo\\' => 'src/']],
            'capabilities' => ['network' => ['api.example.com'], 'filesystem' => ['read:storage/app/x'], 'database' => false, 'shell' => false],
        ]);
        $plugin = Plugin::fromDirectory($dir);

        (new PluginVerifier())->assertAllowedCapabilities($plugin);
        self::assertSame(['api.example.com'], $plugin->capabilities['network']);
        self::assertSame(['read:storage/app/x'], $plugin->capabilities['filesystem']);
    }

    // ─── manifest ─────────────────────────────────────────────────────────

    public function testManifest_InvalidId_Rejected(): void
    {
        $dir = $this->writePluginDir(['id' => 'NoDots', 'name' => 'X', 'version' => '1.0.0',
            'autoload' => ['psr-4' => ['X\\' => 'src/']]]);

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.manifest_invalid');
        Plugin::fromDirectory($dir);
    }

    public function testManifest_BadJson_Rejected(): void
    {
        $dir = $this->tmp . '/bad-json';
        \mkdir($dir, 0775, true);
        \file_put_contents($dir . '/plugin.json', '{not json');

        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.manifest_invalid');
        Plugin::fromDirectory($dir);
    }

    public function testManifest_MissingFile_Rejected(): void
    {
        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.manifest_invalid');
        Plugin::fromDirectory($this->tmp . '/nonexistent');
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    /**
     * @return array{0: Plugin, 1: array<string, mixed>}
     */
    private function signedPlugin(bool $tamper = false): array
    {
        $keypair = \sodium_crypto_sign_keypair();
        $secret = \sodium_crypto_sign_secretkey($keypair);
        $public = \base64_encode(\sodium_crypto_sign_publickey($keypair));

        $manifest = [
            'id' => 'x.demo', 'name' => 'X', 'version' => '1.0.0',
            'author' => ['name' => 'TestVendor'],
            'autoload' => ['psr-4' => ['X\\Demo\\' => 'src/']],
        ];
        $payload = PluginVerifier::canonicalPayload($manifest);
        $manifest['signature'] = 'ed25519:base64:' . \base64_encode(\sodium_crypto_sign_detached($payload, $secret));

        if ($tamper) {
            $manifest['version'] = '2.0.0';
        }

        $dir = $this->writePluginDir($manifest);

        return [
            Plugin::fromDirectory($dir),
            ['require_signature' => true, 'trusted_vendors' => ['TestVendor' => 'ed25519:base64:' . $public]],
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function writePluginDir(array $manifest): string
    {
        $dir = $this->tmp . '/' . \str_replace('.', '-', (string) ($manifest['id'] ?? 'x')) . '-' . \bin2hex(\random_bytes(3));
        \mkdir($dir . '/src', 0775, true);
        \file_put_contents($dir . '/plugin.json', (string) \json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $dir;
    }

    private function rrmdir(string $dir): void
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
