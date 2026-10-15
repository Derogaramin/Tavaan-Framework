<?php

declare(strict_types=1);

namespace Tavaan\Core\Container;

/**
 * تَوان | Tavaan — Core
 * Binding — رکورد یک ثبت در کانتینر.
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final readonly class Binding
{
    /**
     * @param \Closure|class-string|null $concrete پیاده‌سازی (null = خود abstract)
     * @param Lifetime                   $lifetime عمر
     */
    public function __construct(
        public \Closure|string|null $concrete,
        public Lifetime $lifetime = Lifetime::Transient,
    ) {
    }

    public function isShared(): bool
    {
        return $this->lifetime === Lifetime::Singleton || $this->lifetime === Lifetime::Scoped;
    }
}
