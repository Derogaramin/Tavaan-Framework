<?php

declare(strict_types=1);

namespace Tavaan\Engine\Optimizer;

/**
 * تَوان | Tavaan — Optimizer
 * Optimizer — اجرای گروهی cacherها (tvn optimize / optimize:clear).
 *
 * @api 1.0
 * @see docs/03-engines/optimizer.md
 */
final class Optimizer
{
    /** @var array<string, Cacher> */
    private array $cachers = [];

    /** @param array<string, Cacher> $cachers */
    public function __construct(array $cachers = [])
    {
        $this->cachers = $cachers;
    }

    public function register(string $name, Cacher $cacher): void
    {
        $this->cachers[$name] = $cacher;
    }

    /** @return list<string> */
    public function names(): array
    {
        return \array_keys($this->cachers);
    }

    public function optimize(): OptimizerResult
    {
        $timings = [];
        foreach ($this->cachers as $name => $cacher) {
            $start = \microtime(true);
            $cacher->cache();
            $timings[$name] = (\microtime(true) - $start) * 1000;
        }

        return new OptimizerResult($timings);
    }

    public function cache(string $name): void
    {
        $this->cachers[$name]->cache();
    }

    public function clear(?string $name = null): void
    {
        if ($name !== null) {
            $this->cachers[$name]->clear();

            return;
        }

        foreach ($this->cachers as $cacher) {
            $cacher->clear();
        }
    }

    public function isFresh(string $name): bool
    {
        return $this->cachers[$name]->isFresh();
    }

    /** @return array<string, bool> */
    public function freshness(): array
    {
        $out = [];
        foreach ($this->cachers as $name => $cacher) {
            $out[$name] = $cacher->isFresh();
        }

        return $out;
    }
}
