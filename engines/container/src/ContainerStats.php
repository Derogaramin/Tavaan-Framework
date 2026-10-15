<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container;

/**
 * تَوان | Tavaan — Container
 * ContainerStats — آمار Resolve (فقط dev/debug).
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final readonly class ContainerStats
{
    public function __construct(
        public int $makeCalls,
        public int $builds,
        public int $singletonHits,
        public int $scopedHits,
        public int $bindingCount,
    ) {
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'make_calls'    => $this->makeCalls,
            'builds'        => $this->builds,
            'singleton_hits' => $this->singletonHits,
            'scoped_hits'   => $this->scopedHits,
            'bindings'      => $this->bindingCount,
        ];
    }
}
