<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

use Tavaan\Engine\Plugin\Exceptions\PluginException;

/**
 * تَوان | Tavaan — Plugin
 * Plugin — ارزش‌شیء افزونه (manifest پارس‌شده + وضعیت).
 *
 * manifest مطابق DOC-079 §3 اعتبارسنجی می‌شود (id/name/version/tavaan/
 * autoload.psr-4 الزامی؛ provider/permissions/hooks/signature/capabilities اختیاری).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final readonly class Plugin
{
    /** @var array<string, mixed> manifest خام (بدون تغییر) */
    public array $manifest;

    /**
     * @param array<string, mixed>  $manifest
     * @param array<string, string> $psr4   prefix → مسیر نسبی (از manifest)
     * @param list<string>          $hooks
     * @param list<string>          $permissions
     * @param array<string, mixed>  $capabilities نرمال‌شده
     */
    private function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $path,
        public string $authorName,
        public string $authorEmail,
        public ?string $provider,
        public ?string $signature,
        public array $psr4,
        public array $hooks,
        public array $permissions,
        public array $capabilities,
        public array $manifestRaw = [],
        public bool $enabled = false,
        public bool $installed = false,
    ) {
        $this->manifest = $manifestRaw;
    }

    /**
     * خواندن و اعتبارسنجی `plugin.json` یک دایرکتوری.
     *
     * @throws PluginException
     */
    public static function fromDirectory(string $dir, bool $enabled = false, bool $installed = false): self
    {
        $dir = \rtrim($dir, '/\\');
        $file = $dir . '/plugin.json';
        if (!\is_file($file)) {
            throw PluginException::manifestInvalid($dir, 'plugin.json یافت نشد');
        }

        $raw = \file_get_contents($file);
        try {
            /** @var array<string, mixed> $m */
            $m = \json_decode((string) $raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw PluginException::manifestInvalid($dir, 'JSON نامعتبر: ' . $e->getMessage());
        }

        self::assertSchema($m, $dir);

        $autoload = (array) ($m['autoload']['psr-4'] ?? []);
        $psr4 = [];
        foreach ($autoload as $prefix => $path) {
            $psr4[\rtrim((string) $prefix, '\\') . '\\'] = \trim((string) $path, '/\\');
        }

        $author = (array) ($m['author'] ?? []);
        $capabilities = self::normalizeCapabilities((array) ($m['capabilities'] ?? []));

        return new self(
            id: (string) $m['id'],
            name: (string) $m['name'],
            version: (string) $m['version'],
            path: $dir,
            authorName: (string) ($author['name'] ?? ''),
            authorEmail: (string) ($author['email'] ?? ''),
            provider: isset($m['provider']) && \is_string($m['provider']) && $m['provider'] !== '' ? $m['provider'] : null,
            signature: isset($m['signature']) && \is_string($m['signature']) ? $m['signature'] : null,
            psr4: $psr4,
            hooks: \array_values(\array_filter((array) ($m['hooks'] ?? []), 'is_string')),
            permissions: \array_values(\array_filter((array) ($m['permissions'] ?? []), 'is_string')),
            capabilities: $capabilities,
            manifestRaw: $m,
            enabled: $enabled,
            installed: $installed,
        );
    }

    // ─── دسترسی‌ها ─────────────────────────────────────────────────────────

    /** نام دایرکتوری استاندارد افزونه (`acme.tip` → `acme-tip`). */
    public function slug(): string
    {
        return \str_replace('.', '-', $this->id);
    }

    /** اولین prefix فضای‌نام (فضای‌نام اصلی افزونه). */
    public function namespace(): string
    {
        return (string) (\array_key_first($this->psr4) ?? '');
    }

    public function hasVendor(): bool
    {
        return \is_file($this->path . '/vendor/autoload.php');
    }

    /** سازگاری نسخهٔ 선언‌شدهٔ `tavaan` با نسخهٔ جاری فریمورک (constraint ساده). */
    public function supportsFramework(string $frameworkVersion): bool
    {
        $constraint = \trim((string) ($this->manifest['tavaan'] ?? '*'));
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        foreach (\preg_split('/\s+/', $constraint) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            if (!self::satisfies($frameworkVersion, $part)) {
                return false;
            }
        }

        return true;
    }

    // ─── internals ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $m
     *
     * @throws PluginException
     */
    private static function assertSchema(array $m, string $dir): void
    {
        $fail = static function (string $reason) use ($dir): never {
            throw PluginException::manifestInvalid($dir, $reason);
        };

        $id = (string) ($m['id'] ?? '');
        if (\preg_match('/^[a-z0-9]+(\.[a-z0-9\-]+)+$/', $id) !== 1) {
            $fail('id نامعتبر (الگو: vendor.plugin-name)');
        }
        if ((string) ($m['name'] ?? '') === '') {
            $fail('name خالی است');
        }
        $version = (string) ($m['version'] ?? '');
        if (\preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z\-.]+)?(\+[0-9A-Za-z\-.]+)?$/', $version) !== 1) {
            $fail('version باید semver باشد');
        }
        if (!isset($m['autoload']['psr-4']) || (array) $m['autoload']['psr-4'] === []) {
            $fail('autoload.psr-4 الزامی است');
        }
        if (isset($m['provider']) && !\is_string($m['provider'])) {
            $fail('provider باید رشتهٔ class-string باشد');
        }
        foreach (['hooks', 'permissions'] as $listField) {
            if (isset($m[$listField]) && !\is_array($m[$listField])) {
                $fail($listField . ' باید فهرست باشد');
            }
        }
        if (isset($m['capabilities']) && !\is_array($m['capabilities'])) {
            $fail('capabilities باید شیء باشد');
        }
    }

    /**
     * نرمال‌سازی capabilities به شکل قطعی.
     *
     * @param array<string, mixed> $caps
     *
     * @return array{network: list<string>, filesystem: list<string>, database: bool, shell: bool}
     */
    private static function normalizeCapabilities(array $caps): array
    {
        $network = $caps['network'] ?? [];
        $filesystem = $caps['filesystem'] ?? [];

        return [
            'network' => \is_array($network)
                ? \array_values(\array_filter(\array_map('strval', $network), static fn (string $d): bool => $d !== ''))
                : (\is_string($network) && $network !== '' ? [$network] : []),
            'filesystem' => \is_array($filesystem)
                ? \array_values(\array_filter(\array_map('strval', $filesystem), static fn (string $d): bool => $d !== ''))
                : (\is_string($filesystem) && $filesystem !== '' ? [$filesystem] : []),
            'database' => (bool) ($caps['database'] ?? false),
            'shell' => (bool) ($caps['shell'] ?? false),
        ];
    }

    /** ارزیابی یک part از constraint (مثل `>=1.0` یا `<2.0`). */
    private static function satisfies(string $version, string $constraint): bool
    {
        if (\preg_match('/^(>=|<=|>|<|=)?\s*(\d+(\.\d+){0,2})$/', $constraint, $m) !== 1) {
            return true;   // part ناشناخته → سخت‌گیری نکردن بهتر از رد اشتباه است
        }
        $op = $m[1] === '' ? '=' : $m[1];

        return \version_compare($version, $m[2], $op);
    }
}
