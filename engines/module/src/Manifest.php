<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module;

/**
 * تَوان | Tavaan — Module
 * Manifest — رکورد تغییرناپذیر module.json.
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final readonly class Manifest
{
    /**
     * @param array<string, list<string>> $autoload
     * @param list<string>                $providers
     * @param array{php?: string, engines?: list<string>} $requires
     * @param list<string>                $softRequires
     * @param array<string, mixed>        $raw
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $titleFa,
        public string $version,
        public string $namespace,
        public array $autoload,
        public array $providers,
        public array $requires,
        public array $softRequires,
        public ?string $configFile,
        public ?string $configKey,
        public array $raw,
        public string $path,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, string $path): self
    {
        $config = $data['config'] ?? null;

        return new self(
            id: (string) $data['id'],
            type: (string) ($data['type'] ?? 'module'),
            name: (string) ($data['name'] ?? $data['id']),
            titleFa: (string) ($data['title_fa'] ?? $data['name'] ?? $data['id']),
            version: (string) ($data['version'] ?? '0.0.0'),
            namespace: (string) ($data['namespace'] ?? ''),
            autoload: (array) ($data['autoload'] ?? []),
            providers: (array) ($data['providers'] ?? []),
            requires: (array) ($data['requires'] ?? []),
            softRequires: (array) ($data['soft_requires']['engines'] ?? []),
            configFile: \is_array($config) && isset($config['file']) ? (string) $config['file'] : null,
            configKey: \is_array($config) && isset($config['key']) ? (string) $config['key'] : null,
            raw: $data,
            path: $path,
        );
    }

    /** @return list<string> شناسهٔ موتورهای پیش‌نیاز */
    public function engineDependencies(): array
    {
        return (array) ($this->requires['engines'] ?? []);
    }

    /** @return list<string> شناسهٔ ماژول‌های پیش‌نیاز (کوتاه یا کامل) */
    public function moduleDependencies(): array
    {
        return (array) ($this->requires['modules'] ?? []);
    }
}
