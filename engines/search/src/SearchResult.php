<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search;

/**
 * تَوان | Tavaan — Search
 * SearchResult — نتیجهٔ جست‌وجو (hits/total/facets/suggestions).
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class SearchResult implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $hits  هر hit: {id, score, document, highlight?}
     * @param array<string, list<array{value: mixed, count: int}>> $facets
     * @param list<string>               $suggestions
     */
    public function __construct(
        private readonly array $hits = [],
        private readonly int $total = 0,
        private readonly array $facets = [],
        private readonly array $suggestions = [],
        private readonly int $page = 1,
        private readonly int $perPage = 20,
        private readonly float $tookMs = 0.0,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function hits(): array
    {
        return $this->hits;
    }

    /** @return list<array<string, mixed>> فقط سند‌ها (بدون score) */
    public function documents(): array
    {
        return \array_map(static fn (array $h): array => (array) ($h['document'] ?? []), $this->hits);
    }

    public function total(): int
    {
        return $this->total;
    }

    /** @return array<string, list<array{value: mixed, count: int}>> */
    public function facets(): array
    {
        return $this->facets;
    }

    /** @return list<string> */
    public function suggestions(): array
    {
        return $this->suggestions;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function lastPage(): int
    {
        return \max(1, (int) \ceil($this->total / \max(1, $this->perPage)));
    }

    public function tookMs(): float
    {
        return $this->tookMs;
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function count(): int
    {
        return \count($this->hits);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->hits);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'hits' => $this->hits,
            'total' => $this->total,
            'facets' => $this->facets,
            'suggestions' => $this->suggestions,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'last_page' => $this->lastPage(),
            'took_ms' => \round($this->tookMs, 2),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
