<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Module
 * KitManifest — رکورد تغییرناپذیر kit.json (DOC-090 §2.1).
 */

namespace Tavaan\Engine\Module\Kit;

/**
 * KitManifest — manifest یک کیت اپلیکیشنی.
 *
 * @api 1.0
 * @see docs/05-applications/README.md
 */
final readonly class KitManifest
{
    /**
     * @param list<array{id: string, required: bool}> $modules
     * @param array{required?: list<string>, recommended?: list<string>} $engines
     * @param array<string, mixed> $requirements
     * @param array<string, mixed> $install
     * @param list<string> $roles
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $titleFa,
        public string $titleEn,
        public string $version,
        public string $architecture,
        public string $description,
        public array $modules,
        public array $engines,
        public string $theme,
        public string $defaultPreset,
        public array $roles,
        public ?string $navigation,
        public ?string $demoData,
        public array $requirements,
        public array $install,
        public array $raw,
        public string $path,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws KitException اگر فیلدهای الزامی نامعتبر باشند
     */
    public static function fromArray(array $data, string $path): self
    {
        $id = (string) ($data['id'] ?? '');
        if (\preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/', $id) !== 1) {
            throw KitException::invalidManifest($path, 'id نامعتبر (الگو: vendor.kit)');
        }
        if (!isset($data['version']) || \preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', (string) $data['version']) !== 1) {
            throw KitException::invalidManifest($path, 'version نامعتبر (SemVer)');
        }

        $modules = [];
        foreach ((array) ($data['modules'] ?? []) as $module) {
            if (\is_array($module) && isset($module['id']) && \is_string($module['id'])) {
                $modules[] = ['id' => $module['id'], 'required' => (bool) ($module['required'] ?? true)];
            } elseif (\is_string($module) && $module !== '') {
                $modules[] = ['id' => $module, 'required' => true];
            }
        }

        $segments = \explode('.', $id);
        $slug = (string) \end($segments);

        return new self(
            id: $id,
            slug: (string) ($data['slug'] ?? $slug),
            titleFa: (string) ($data['title_fa'] ?? $data['title_en'] ?? $id),
            titleEn: (string) ($data['title_en'] ?? $id),
            version: (string) $data['version'],
            architecture: (string) ($data['architecture'] ?? 'modular'),
            description: (string) ($data['description'] ?? ''),
            modules: $modules,
            engines: (array) ($data['engines'] ?? []),
            theme: (string) ($data['theme'] ?? ''),
            defaultPreset: (string) ($data['default_preset'] ?? 'standard'),
            roles: \array_map('strval', (array) ($data['roles'] ?? [])),
            navigation: isset($data['navigation']) ? (string) $data['navigation'] : null,
            demoData: isset($data['demo_data']) ? (string) $data['demo_data'] : null,
            requirements: (array) ($data['requirements'] ?? []),
            install: (array) ($data['install'] ?? []),
            raw: $data,
            path: $path,
        );
    }

    /** @return list<string> شناسهٔ ماژول‌های الزامی */
    public function requiredModules(): array
    {
        $out = [];
        foreach ($this->modules as $module) {
            if ($module['required']) {
                $out[] = $module['id'];
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        $out = [];
        foreach ($this->modules as $module) {
            if (!$module['required']) {
                $out[] = $module['id'];
            }
        }

        return $out;
    }
}
