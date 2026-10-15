<?php

declare(strict_types=1);

namespace Tavaan\Engine\View;

use Tavaan\Engine\View\Exceptions\ViewException;

/**
 * تَوان | Tavaan — View
 * ViewFinder — یافتن قالب با سلسله‌مراتب Override (DOC-043 §6).
 *
 * ترتیب: vendor override → namespace path → مسیرهای سراسری.
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class ViewFinder
{
    /** @var list<string> */
    private array $paths = [];

    /** @var array<string, list<string>> namespace → paths */
    private array $namespaces = [];

    /** @var array<string, string> کش name → path */
    private array $found = [];

    /**
     * @param list<string> $paths
     */
    public function __construct(array $paths = [], private readonly string $extension = '.tv.php')
    {
        foreach ($paths as $path) {
            $this->addLocation($path);
        }
    }

    public function addLocation(string $path): void
    {
        if ($path !== '' && !\in_array($path, $this->paths, true)) {
            $this->paths[] = $path;
        }
        $this->found = [];
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace][] = $path;
        $this->found = [];
    }

    /** @return list<string> */
    public function paths(): array
    {
        return $this->paths;
    }

    public function exists(string $name): bool
    {
        try {
            $this->find($name);

            return true;
        } catch (ViewException) {
            return false;
        }
    }

    /**
     * یافتن مسیر فایل قالب.
     *
     * @throws ViewException
     */
    public function find(string $name): string
    {
        if (isset($this->found[$name])) {
            return $this->found[$name];
        }

        // 🔒 Path Traversal: نام قالب نباید .. یا / مطلق داشته باشد
        if (\str_contains($name, '..') || \str_starts_with($name, '/')) {
            throw ViewException::notFound($name);
        }

        $candidates = [];

        if (\str_contains($name, '::')) {
            [$namespace, $view] = \explode('::', $name, 2);
            // 1) vendor override
            foreach ($this->paths as $path) {
                $candidates[] = $path . '/vendor/' . $namespace . '/' . \str_replace('.', '/', $view);
            }
            // 2) namespace paths
            foreach ($this->namespaces[$namespace] ?? [] as $path) {
                $candidates[] = $path . '/' . \str_replace('.', '/', $view);
            }
        } else {
            foreach ($this->paths as $path) {
                $candidates[] = $path . '/' . \str_replace('.', '/', $name);
            }
        }

        foreach ($candidates as $candidate) {
            foreach ([$candidate . $this->extension, $candidate . '.php'] as $file) {
                if (\is_file($file)) {
                    return $this->found[$name] = $file;
                }
            }
        }

        throw ViewException::notFound($name);
    }

    public function flushCache(): void
    {
        $this->found = [];
    }
}
