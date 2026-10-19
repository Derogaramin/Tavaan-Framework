<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

use Tavaan\Engine\Plugin\Exceptions\PluginException;

/**
 * تَوان | Tavaan — Plugin
 * PluginVerifier — امضای Ed25519 + hash فایل‌ها (plugins.lock) + capabilities
 * (DOC-079 §4).
 *
 * امضا روی «payload کانویک» نشسته می‌شود: manifest بدون فیلد `signature`،
 * با ksort بازگشتی و JSON_UNESCAPED_SLASHES|UNESCAPED_UNICODE — ابزار امضای
 * فروشنده باید دقیقاً از `PluginVerifier::canonicalPayload()` استفاده کند.
 *
 * کلیدهای مورد اعتماد: `config/plugins.php → trusted_vendors` به شکل
 * `'Author Name' => 'ed25519:base64:<PUBKEY-32B>'`.
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginVerifier
{
    /**
     * @param array<string, mixed> $config کلیدهای plugins.* (trusted_vendors/require_signature/sandbox/…)
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?PluginsLock $lock = null,
    ) {
    }

    /**
     * payload کانویک برای امضا/verify (همه‌جا یکسان — ابزار فروشنده هم همین را دارد).
     *
     * @param array<string, mixed> $manifest
     */
    public static function canonicalPayload(array $manifest): string
    {
        unset($manifest['signature']);

        return (string) \json_encode(
            self::ksortRecursive($manifest),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * اعتبارسنجی کامل: امضا (در صورت سیاست) + capabilities + hash فایل‌ها.
     *
     * @throws PluginException
     */
    public function verify(Plugin $plugin): void
    {
        $this->verifySignature($plugin);
        $this->assertAllowedCapabilities($plugin);
        $this->verifyFileHashes($plugin);
    }

    /**
     * فقط امضا + capabilities (نصب — پیش از آنکه lock ساخته شود).
     *
     * @throws PluginException
     */
    public function verifyForInstall(Plugin $plugin): void
    {
        $this->verifySignature($plugin);
        $this->assertAllowedCapabilities($plugin);
    }

    /**
     * بررسی امضای Ed25519 (AC-PLG-09: بدون امضا + require_signature → رد).
     *
     * @throws PluginException
     */
    public function verifySignature(Plugin $plugin): void
    {
        $require = (bool) ($this->config['require_signature'] ?? true);

        if ($plugin->signature === null || $plugin->signature === '') {
            if ($require) {
                throw PluginException::signatureMissing($plugin->id);
            }

            return;
        }

        if (!\extension_loaded('sodium')) {
            throw PluginException::signatureInvalid($plugin->id, 'افزونهٔ sodium برای verify امضا لازم است');
        }

        /** @var array<string, string> $trusted */
        $trusted = (array) ($this->config['trusted_vendors'] ?? []);
        $publicSpec = $trusted[$plugin->authorName] ?? null;
        if ($publicSpec === null) {
            throw PluginException::vendorUntrusted($plugin->authorName);
        }

        $publicKey = self::decodeKeySpec($publicSpec, 'trusted_vendors[' . $plugin->authorName . ']');
        $signature = self::decodeKeySpec($plugin->signature, 'signature');
        $payload = self::canonicalPayload($plugin->manifest);

        $valid = false;
        try {
            $valid = \sodium_crypto_sign_verify_detached($signature, $payload, $publicKey);
        } catch (\Throwable) {
            $valid = false;
        }

        if (!$valid) {
            throw PluginException::signatureInvalid($plugin->id);
        }
    }

    /**
     * بررسی hash فایل‌ها با plugins.lock (تشخیص دستکاری پس از نصب).
     *
     * @throws PluginException
     */
    public function verifyFileHashes(Plugin $plugin): void
    {
        $lock = $this->lock;
        if ($lock === null) {
            return;   // lock هنوز ساخته نشده (نصب) — verifyForInstall این مسیر است
        }

        $entry = $lock->entry($plugin->id);
        if ($entry === null) {
            throw PluginException::lockMissing($plugin->id);
        }

        /** @var array<string, string> $expected */
        $expected = (array) ($entry['files'] ?? []);
        $actual = PluginsLock::hashDirectory($plugin->path);

        $problems = [];
        foreach ($expected as $file => $hash) {
            if (!isset($actual[$file])) {
                $problems[] = 'missing: ' . $file;
            } elseif (!\hash_equals((string) $hash, (string) $actual[$file])) {
                $problems[] = 'modified: ' . $file;
            }
        }
        foreach ($actual as $file => $hash) {
            unset($hash);
            if (!isset($expected[$file])) {
                $problems[] = 'unexpected: ' . $file;
            }
        }

        if ($problems !== []) {
            throw PluginException::lockTampered($plugin->id, \implode('، ', \array_slice($problems, 0, 10)));
        }
    }

    /**
     * سیاست capabilities (DOC-079 §8 sandbox):
     * - `database`/`shell` فقط اگر سیاست مجوز دهد
     * - `network` وقتی allowlist فعال است باید دامنه‌های صریح باشد (بدون `*`)
     * - `filesystem` فقط `read:/write:` داخل base_path (بدون `..`)
     *
     * @throws PluginException
     */
    public function assertAllowedCapabilities(Plugin $plugin): void
    {
        /** @var array<string, mixed> $sandbox */
        $sandbox = (array) ($this->config['sandbox'] ?? []);
        $caps = $plugin->capabilities;

        if ($caps['database'] && !(bool) ($sandbox['database'] ?? false)) {
            throw PluginException::capabilityDenied($plugin->id, 'database', 'سیاست sandbox دسترسی مستقیم DB نمی‌دهد (فقط Contract)');
        }
        if ($caps['shell'] && !(bool) ($sandbox['shell'] ?? false)) {
            throw PluginException::capabilityDenied($plugin->id, 'shell', 'سیاست sandbox اجرای shell نمی‌دهد');
        }

        if ((bool) ($sandbox['network_allowlist'] ?? true)) {
            foreach ($caps['network'] as $domain) {
                if ($domain === '*' || $domain === '' || \str_contains($domain, '..')) {
                    throw PluginException::capabilityDenied($plugin->id, 'network', 'دامنهٔ نامعتبر در allowlist: ' . $domain);
                }
            }
        }

        foreach ($caps['filesystem'] as $spec) {
            if (\preg_match('/^(read|write):(.+)$/', $spec, $m) !== 1) {
                throw PluginException::capabilityDenied($plugin->id, 'filesystem', 'قالب نامعتبر (باید read:/write: باشد): ' . $spec);
            }
            if (\str_contains($m[2], '..')) {
                throw PluginException::capabilityDenied($plugin->id, 'filesystem', 'مسیر شامل .. است: ' . $spec);
            }
        }
    }

    /** رمزگشایی `ed25519:base64:<DATA>` به بایت خام. */
    private static function decodeKeySpec(string $spec, string $what): string
    {
        $parts = \explode(':', $spec);
        if (\count($parts) !== 3 || $parts[0] !== 'ed25519' || $parts[1] !== 'base64') {
            throw new \InvalidArgumentException(\sprintf('قالب %s باید ed25519:base64:<DATA> باشد.', $what));
        }
        $decoded = \base64_decode($parts[2], true);
        if ($decoded === false || $decoded === '') {
            throw new \InvalidArgumentException(\sprintf('base64 نامعتبر در %s.', $what));
        }

        return $decoded;
    }

    /** @param array<array-key, mixed> $data @return array<array-key, mixed> */
    private static function ksortRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = self::ksortRecursive($value);
            }
        }
        \ksort($data);

        return $data;
    }
}
