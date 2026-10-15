<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

/**
 * تَوان | Tavaan — Routing
 * TrieNode — گره Compiled Trie (DOC-040 §5.2).
 *
 * @api 1.0 (internal)
 * @see docs/03-engines/router.md
 */
final class TrieNode
{
    /** @var array<string, self> segment ثابت → گره */
    public array $static = [];

    /** @var list<array{edge: string, name: string, regex: string, node: self, specificity: int, order: int}> یال‌های پارامتریک (مرتب: محدود اول) */
    public array $params = [];

    /** @var array{name: string, regex: string, node: self}|null یال wildcard (.*) */
    public ?array $wildcard = null;

    /** @var array<string, list<array{route: Route, specificity: int, order: int}>> method → handlerها */
    public array $handlers = [];

    public function isEmpty(): bool
    {
        return $this->static === [] && $this->params === [] && $this->wildcard === null && $this->handlers === [];
    }
}
