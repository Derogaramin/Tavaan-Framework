<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event;

/**
 * تَوان | Tavaan — Event
 * TrieNode — گره Segment Trie برای تطبیق Wildcard (DOC-033 §5.1).
 *
 * @api 1.0 (internal)
 * @see docs/03-engines/event.md
 */
final class TrieNode
{
    /** @var array<string, self> */
    public array $children = [];

    /** @var list<RegisteredListener> */
    public array $listeners = [];

    public function child(string $segment): self
    {
        return $this->children[$segment] ??= new self();
    }

    public function hasChild(string $segment): bool
    {
        return isset($this->children[$segment]);
    }

    public function isEmpty(): bool
    {
        return $this->children === [] && $this->listeners === [];
    }
}
