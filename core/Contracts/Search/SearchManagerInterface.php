<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Search;

use Tavaan\Engine\Search\SearchResult;

/**
 * تَوان | Tavaan — Contracts
 * SearchManagerInterface — قرارداد مدیر جست‌وجو (DOC-052 §14).
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
interface SearchManagerInterface
{
    public function driver(?string $index = null): SearchDriverInterface;

    /** @param object|array<string, mixed> $document */
    public function index(string $index, mixed $document, ?string $docId = null): void;

    /** @param iterable<int|string, object|array<string, mixed>> $documents */
    public function indexMany(string $index, iterable $documents): int;

    /** @param object|array<string, mixed>|string|int $idOrDocument */
    public function delete(string $index, mixed $idOrDocument): void;

    /** @param array<string, mixed> $options */
    public function search(string $index, string $query, array $options = []): SearchResult;

    /** @return list<string> */
    public function suggest(string $index, string $query, int $limit = 5): array;

    public function createIndex(string $index): void;

    public function dropIndex(string $index): void;

    public function indexExists(string $index): bool;

    public function flush(string $index): void;

    public function count(string $index): int;

    public function reindex(string $index, int $chunk = 200): int;

    public function extend(string $name, \Closure $factory): void;
}
