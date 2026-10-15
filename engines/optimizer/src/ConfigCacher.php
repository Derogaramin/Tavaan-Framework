<?php

declare(strict_types=1);

namespace Tavaan\Engine\Optimizer;

use Tavaan\Core\Contracts\Config\ConfigCacheInterface;

/**
 * تَوان | Tavaan — Optimizer
 * ConfigCacher — کش کل پیکربندی (tvn config:cache).
 *
 * @api 1.0
 * @see docs/03-engines/optimizer.md
 */
final class ConfigCacher implements Cacher
{
    public function __construct(
        private readonly ConfigCacheInterface $cache,
        private readonly string $file,
    ) {
    }

    public function cache(): void
    {
        $this->cache->cache($this->file);
    }

    public function clear(): void
    {
        $this->cache->clear($this->file);
    }

    public function isFresh(): bool
    {
        return $this->cache->isCached($this->file);
    }
}
