<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module;

use Tavaan\Engine\Module\Exceptions\ModuleException;

/**
 * تَوان | Tavaan — Module
 * ModuleRegistry — کشف، ثبت، جست‌وجو و مرتب‌سازی توپولوژیک ماژول‌ها/موتورها.
 *
 * منابع کشف: `engines/*&#47;module.json` و `modules/*&#47;module.json` و kits.
 * وضعیت فعال/غیرفعال از config (modules.disabled) + فایل وضعیت در storage.
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class ModuleRegistry
{
    /** @var array<string, Module> کلید = شناسهٔ کامل (tavaan.engine.cache) */
    private array $modules = [];

    /** @var array<string, string> شناسهٔ کامل → کوتاه (cache) */
    private array $shortIds = [];

    private bool $discovered = false;

    /**
     * @param list<string>          $paths        مسیرهای کشف (مطلق)
     * @param array<string, mixed>  $statusMap    وضعیت‌های پایدار (id → active|disabled)
     * @param list<string>          $disabled     از config modules.disabled
     */
    public function __construct(
        private readonly array $paths = [],
        private array $statusMap = [],
        private readonly array $disabled = [],
        private readonly ManifestParser $parser = new ManifestParser(),
        private readonly TopologicalSorter $sorter = new TopologicalSorter(),
    ) {
    }

    /** کشف ماژول‌ها از مسیرها (idempotent). */
    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }
        $this->discovered = true;

        foreach ($this->paths as $path) {
            foreach (\glob(\rtrim($path, '/') . '/*/module.json') ?: [] as $manifestFile) {
                $manifest = $this->parser->parse($manifestFile);

                $enabled = !\in_array($manifest->id, $this->disabled, true)
                    && ($this->statusMap[$manifest->id] ?? 'active') !== 'disabled';

                $module = new Module($manifest, $enabled);
                $this->modules[$manifest->id] = $module;
                $this->shortIds[$this->shortId($manifest->id)] = $manifest->id;
            }
        }
    }

    /** @return array<string, Module> */
    public function all(): array
    {
        $this->discover();

        return $this->modules;
    }

    public function get(string $id): Module
    {
        $this->discover();

        $full = $this->modules[$id] ?? $this->modules[$this->shortIds[$id] ?? ''] ?? null;
        if ($full === null) {
            throw ModuleException::notFound($id);
        }

        return $full;
    }

    public function has(string $id): bool
    {
        $this->discover();

        return isset($this->modules[$id]) || isset($this->shortIds[$id]);
    }

    public function enabled(string $id): bool
    {
        return $this->get($id)->enabled();
    }

    /** مرتب‌سازی توپولوژیک ماژول‌های فعال. @return list<Module> */
    public function sorted(): array
    {
        $active = [];
        foreach ($this->all() as $module) {
            if ($module->enabled()) {
                $active[$this->shortId($module->id())] = $module;
            }
        }

        return $this->sorter->sort($active);
    }

    /** @return list<string> وابستگی‌های مستقیم */
    public function dependencies(string $id): array
    {
        return $this->get($id)->dependencies();
    }

    /** @return list<string> ماژول‌هایی که به این ماژول وابسته‌اند */
    public function dependentsOf(string $id): array
    {
        $this->discover();
        $short = $this->shortId($this->get($id)->id());

        $dependents = [];
        foreach ($this->modules as $otherId => $module) {
            if ($module->id() !== $this->get($id)->id() && \in_array($short, $module->dependencies(), true)) {
                $dependents[] = $otherId;
            }
        }

        return $dependents;
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $module = $this->get($id);
        $module->setEnabled($enabled);
        $this->statusMap[$module->id()] = $enabled ? 'active' : 'disabled';
    }

    /** @return array<string, string> */
    public function statusMap(): array
    {
        return $this->statusMap;
    }

    /** تعداد ماژول‌های فعال/کل. @return array{enabled: int, total: int} */
    public function counts(): array
    {
        $enabled = 0;
        foreach ($this->all() as $m) {
            if ($m->enabled()) {
                $enabled++;
            }
        }

        return ['enabled' => $enabled, 'total' => \count($this->modules)];
    }

    /** tavaan.engine.cache → cache ؛ tavaan.shop → shop */
    private function shortId(string $id): string
    {
        $parts = \explode('.', $id);

        return \end($parts) ?: $id;
    }
}
