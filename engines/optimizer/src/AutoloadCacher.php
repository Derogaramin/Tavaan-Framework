<?php

declare(strict_types=1);

namespace Tavaan\Engine\Optimizer;

use Tavaan\Core\Autoloading\AutoloaderRegistry;

/**
 * تَوان | Tavaan — Optimizer
 * AutoloadCacher — تولید ClassMap cache (tvn autoload:cache).
 *
 * @api 1.0
 * @see docs/03-engines/optimizer.md
 */
final class AutoloadCacher implements Cacher
{
    /** @param list<string> $exclude */
    public function __construct(
        private readonly string $file,
        private readonly array $exclude = ['tests/*', 'docs/*', 'examples/*', 'benchmarks/*'],
    ) {
    }

    public function cache(): void
    {
        AutoloaderRegistry::get()->dumpClassMap($this->file, $this->exclude);
    }

    public function clear(): void
    {
        if (\is_file($this->file)) {
            @\unlink($this->file);
        }
    }

    public function isFresh(): bool
    {
        return \is_file($this->file);
    }
}
