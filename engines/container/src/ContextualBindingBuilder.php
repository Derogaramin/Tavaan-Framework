<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container;

use Tavaan\Core\Contracts\Container\ContextualBindingBuilder as ContextualBindingBuilderInterface;

/**
 * تَوان | Tavaan — Container
 * ContextualBindingBuilder — fluent when()->needs()->give().
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final class ContextualBindingBuilder implements ContextualBindingBuilderInterface
{
    /**
     * @param \Closure(Container, string, \Closure|string|array): void $binder
     */
    public function __construct(
        private readonly \Closure $binder,
        private readonly string $consumer,
        private string $abstract = '',
    ) {
    }

    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;

        return $this;
    }

    public function give(\Closure|string|array $implementation): void
    {
        ($this->binder)($this->consumer, $this->abstract, $implementation);
    }
}
