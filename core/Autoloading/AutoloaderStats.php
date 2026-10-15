<?php

declare(strict_types=1);

namespace Tavaan\Core\Autoloading;

/**
 * تَوان | Tavaan — Core
 * AutoloaderStats — آمار ضربت/خطا/ClassMap برای Diagnostics.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final readonly class AutoloaderStats
{
    public function __construct(
        public int $classMapHits,
        public int $prefixHits,
        public int $wildcardHits,
        public int $misses,
        public int $loadedFiles,
    ) {
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'classmap_hits' => $this->classMapHits,
            'prefix_hits'   => $this->prefixHits,
            'wildcard_hits' => $this->wildcardHits,
            'misses'        => $this->misses,
            'loaded_files'  => $this->loadedFiles,
        ];
    }
}
