<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event;

/**
 * تَوان | Tavaan — Event
 * WildcardMatcher — تطبیق الگوهای `shop.order.*` با Segment Trie.
 *
 * پیچیدگی: ساخت O(n·k)؛ تطبیق O(2^k) بدترین حالت (k = تعداد segment).
 * خروجی قطعی: priority DESC سپس seq ASC.
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final class WildcardMatcher
{
    private TrieNode $root;

    private int $size = 0;

    public function __construct()
    {
        $this->root = new TrieNode();
    }

    public function add(string $pattern, RegisteredListener $listener): void
    {
        $node = $this->root;
        foreach (\explode('.', $pattern) as $seg) {
            $node = $node->child($seg);
        }
        $node->listeners[] = $listener;
        $this->size++;
    }

    public function remove(string $pattern): void
    {
        $this->removeWalk($this->root, \explode('.', $pattern), 0);
    }

    /**
     * تطبیق نام رویداد و بازگرداندن Listenerهای مرتب‌شده.
     *
     * @return list<RegisteredListener>
     */
    public function match(string $eventName): array
    {
        if ($this->size === 0) {
            return [];
        }

        $found = [];
        $this->walk($this->root, \explode('.', $eventName), 0, $found);

        if ($found === []) {
            return [];
        }

        // 🔒 قطعیت: priority DESC، سپس seq ASC
        \usort($found, static fn (RegisteredListener $a, RegisteredListener $b): int
            => $b->priority <=> $a->priority ?: $a->seq <=> $b->seq);

        return $found;
    }

    public function isEmpty(): bool
    {
        return $this->size === 0;
    }

    /** @return list<string> همهٔ الگوهای ثبت‌شده */
    public function patterns(): array
    {
        $out = [];
        $this->collectPatterns($this->root, '', $out);

        return $out;
    }

    /**
     * @param list<string>          $segments
     * @param list<RegisteredListener> $found
     */
    private function walk(TrieNode $node, array $segments, int $i, array &$found): void
    {
        if ($i === \count($segments)) {
            foreach ($node->listeners as $l) {
                $found[] = $l;
            }

            return;
        }

        $seg = $segments[$i];
        if (isset($node->children[$seg])) {
            $this->walk($node->children[$seg], $segments, $i + 1, $found);
        }
        if (isset($node->children['*'])) {
            $this->walk($node->children['*'], $segments, $i + 1, $found);
        }
    }

    /**
     * @param list<string> $segments
     */
    private function removeWalk(TrieNode $node, array $segments, int $i): bool
    {
        if ($i === \count($segments)) {
            $removed = \count($node->listeners);
            $node->listeners = [];
            $this->size -= $removed;

            return $node->isEmpty();
        }

        $seg = $segments[$i];
        if (!isset($node->children[$seg])) {
            return false;
        }

        if ($this->removeWalk($node->children[$seg], $segments, $i + 1)) {
            unset($node->children[$seg]);
        }

        return $node->isEmpty();
    }

    /**
     * @param list<string> $out
     */
    private function collectPatterns(TrieNode $node, string $prefix, array &$out): void
    {
        foreach ($node->listeners as $l) {
            $out[] = $l->pattern;
        }
        foreach ($node->children as $seg => $child) {
            $this->collectPatterns($child, $prefix . $seg . '.', $out);
        }
    }
}
