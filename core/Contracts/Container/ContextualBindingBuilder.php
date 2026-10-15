<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Container;

/**
 * قرارداد Contextual Binding (وابستگی به مصرف‌کننده).
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
interface ContextualBindingBuilder
{
    public function needs(string $abstract): self;

    /** @param \Closure|class-string|array<array-key, mixed> $implementation */
    public function give(\Closure|string|array $implementation): void;
}
