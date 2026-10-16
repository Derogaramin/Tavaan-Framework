<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search\Drivers;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Search\SearchDriverInterface;
use Tavaan\Engine\Search\Exceptions\SearchException;
use Tavaan\Engine\Search\Highlighter;
use Tavaan\Engine\Search\Persian;
use Tavaan\Engine\Search\SearchResult;

/**
 * تَوان | Tavaan — Search
 * SqliteFtsDriver — جست‌وجوی Full-Text با FTS5 (خودنوشته، بدون وابستگی).
 *
 * ساختار جدول مجازی به ازای هر ایندکس:
 * ```sql
 * CREATE VIRTUAL TABLE tvn_fts_{index} USING fts5(
 *   doc_id UNINDEXED, {searchable...}, {filterable/sortable... UNINDEXED}, payload UNINDEXED
 * )
 * ```
 * متن searchable پیش از ذخیره با Persian::normalize یکسان می‌شود تا
 * تطبیق ی/ک/نیم‌فاصله درست کار کند. دادهٔ اصلی در payload (JSON) می‌ماند.
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class SqliteFtsDriver implements SearchDriverInterface
{
    /**
     * @param array<string, mixed> $config کلید search در config
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly array $config = [],
    ) {
        if (!$this->fts5Available()) {
            throw SearchException::driverUnavailable('sqlite', 'SQLite FTS5 is not available in this build');
        }
    }

    /** کاوش قابلیت FTS5 (بدون کش). */
    public function fts5Available(): bool
    {
        try {
            $this->connection->statement('CREATE VIRTUAL TABLE IF NOT EXISTS tvn_fts5_probe USING fts5(x)');
            $this->connection->statement('DROP TABLE IF EXISTS tvn_fts5_probe');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function createIndex(string $index): void
    {
        $this->validate($index);
        $cols = $this->columnsFor($index);

        $defs = ['doc_id UNINDEXED'];
        foreach ($cols['searchable'] as $f => $_boost) {
            $defs[] = $this->quoteIdent($f);
        }
        foreach ($cols['extra'] as $f) {
            $defs[] = $this->quoteIdent($f) . ' UNINDEXED';
        }
        $defs[] = 'payload UNINDEXED';

        $sql = 'CREATE VIRTUAL TABLE IF NOT EXISTS ' . $this->table($index) . ' USING fts5(' . \implode(', ', $defs) . ')';
        $this->connection->statement($sql);
    }

    public function dropIndex(string $index): void
    {
        $this->validate($index);
        $this->connection->statement('DROP TABLE IF EXISTS ' . $this->table($index));
    }

    public function indexExists(string $index): bool
    {
        $this->validate($index);
        $rows = $this->connection->select(
            "SELECT name FROM sqlite_master WHERE type IN ('table','view') AND name = ?",
            [\trim($this->table($index), '"')],
        );

        return $rows !== [];
    }

    public function index(string $index, string $docId, array $data): void
    {
        $this->indexMany($index, [$docId => $data]);
    }

    public function indexMany(string $index, array $docs): void
    {
        $this->validate($index);
        $cols = $this->columnsFor($index);
        $table = $this->table($index);

        foreach ($docs as $docId => $data) {
            $docId = (string) $docId;
            $data = (array) $data;
            $this->connection->statement('DELETE FROM ' . $table . ' WHERE doc_id = ?', [$docId]);

            $columns = ['doc_id'];
            $values = [$docId];
            foreach ($cols['searchable'] as $f => $_boost) {
                $columns[] = $this->quoteIdent($f);
                $values[] = Persian::normalize($this->stringify($data[$f] ?? ''));
            }
            foreach ($cols['extra'] as $f) {
                $columns[] = $this->quoteIdent($f);
                $v = $data[$f] ?? null;
                $values[] = \is_scalar($v) || $v === null ? $v : \json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
            $columns[] = 'payload';
            $values[] = \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

            $placeholders = \implode(', ', \array_fill(0, \count($columns), '?'));
            $this->connection->statement(
                'INSERT INTO ' . $table . ' (' . \implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
                $values,
            );
        }
    }

    public function delete(string $index, string $docId): void
    {
        $this->validate($index);
        $this->connection->statement('DELETE FROM ' . $this->table($index) . ' WHERE doc_id = ?', [$docId]);
    }

    public function flush(string $index): void
    {
        $this->validate($index);
        $this->connection->statement('DELETE FROM ' . $this->table($index));
    }

    public function count(string $index): int
    {
        $this->validate($index);
        $rows = $this->connection->select('SELECT COUNT(*) AS n FROM ' . $this->table($index));

        return (int) ($rows[0]['n'] ?? 0);
    }

    public function search(string $index, string $query, array $options = []): SearchResult
    {
        $started = \hrtime(true);
        $this->validate($index);

        $maxLen = (int) ($this->config['max_query_length'] ?? 500);
        if (\mb_strlen($query) > $maxLen) {
            throw SearchException::queryTooLong($maxLen);
        }

        $minLength = (int) ($this->config['min_token_length'] ?? 2);
        $maxTokens = (int) ($this->config['max_tokens'] ?? 64);
        $tokens = \array_slice(Persian::tokenize($query, $minLength), 0, $maxTokens);

        $table = $this->table($index);
        $cols = $this->columnsFor($index);

        [$where, $bindings] = $this->buildFilters($index, (array) ($options['filters'] ?? []), $cols);

        $page = \max(1, (int) ($options['page'] ?? 1));
        $perPage = \max(1, \min((int) ($options['per_page'] ?? 20), (int) ($this->config['max_per_page'] ?? 100)));

        if ($tokens === []) {
            return new SearchResult([], 0, [], $this->suggestForEmpty($index, $query), $page, $perPage, (\hrtime(true) - $started) / 1e6);
        }

        $match = $this->buildMatch($tokens);

        // 🔒 ranking با bm25() — مقدار منفی‌تر = مرتبط‌تر
        $whereSql = $table . ' MATCH ?' . ($where === '' ? '' : ' AND ' . $where);

        $countRows = $this->connection->select(
            'SELECT COUNT(*) AS n FROM ' . $table . ' WHERE ' . $whereSql,
            [$match, ...$bindings],
        );
        $total = (int) ($countRows[0]['n'] ?? 0);

        $orderSql = $this->buildOrder($index, (array) ($options['sort'] ?? []), $cols);
        $rows = $this->connection->select(
            'SELECT doc_id, bm25(' . $table . ') AS rank, payload FROM ' . $table
            . ' WHERE ' . $whereSql . ' ORDER BY ' . $orderSql
            . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            [$match, ...$bindings],
        );

        $facets = $this->computeFacets($table, $match, $bindings, $whereSql, (array) ($options['facets'] ?? []), $cols);

        $highlightFields = (array) ($options['highlight'] ?? []);
        $hits = [];
        foreach ($rows as $row) {
            $payload = \json_decode((string) ($row['payload'] ?? '{}'), true) ?: [];
            $hit = [
                'id' => (string) $row['doc_id'],
                'score' => \round(-(float) $row['rank'], 6),   // bm25 منفی است → score مثبت
                'document' => (array) $payload,
            ];
            if ($highlightFields !== []) {
                $marked = [];
                foreach ($highlightFields as $hf) {
                    $raw = $this->stringify($payload[(string) $hf] ?? '');
                    if ($raw !== '') {
                        $marked[(string) $hf] = Highlighter::highlight($raw, $tokens);
                    }
                }
                if ($marked !== []) {
                    $hit['highlight'] = $marked;
                }
            }
            $hits[] = $hit;
        }

        $suggestions = $total === 0 ? $this->suggest($index, $query, 3) : [];

        return new SearchResult(
            hits: $hits,
            total: $total,
            facets: $facets,
            suggestions: $suggestions,
            page: $page,
            perPage: $perPage,
            tookMs: (\hrtime(true) - $started) / 1e6,
        );
    }

    public function suggest(string $index, string $query, int $limit = 5): array
    {
        $this->validate($index);
        $normalized = \mb_strtolower(Persian::normalize($query));
        $prefix = \trim(\preg_replace('/[^\p{L}\p{N}\s]+/u', '', $normalized) ?? '');
        if ($prefix === '') {
            return [];
        }
        $first = (string) (\preg_split('/\s+/u', $prefix, -1, PREG_SPLIT_NO_EMPTY)[0] ?? '');
        if ($first === '') {
            return [];
        }

        $table = $this->table($index);
        $cols = $this->columnsFor($index);
        $out = [];
        foreach (\array_keys($cols['searchable']) as $field) {
            try {
                // FTS5 prefix query برای autocomplete —
                // 🔒 $first فقط حرف/عدد است (پاک‌سازی بالا) پس prefix بدون quote امن است
                $rows = $this->connection->select(
                    'SELECT ' . $this->quoteIdent($field) . ' AS v FROM ' . $table
                    . ' WHERE ' . $table . " MATCH ? LIMIT " . \max(1, $limit),
                    [$first . '*'],
                );
            } catch (\Throwable) {
                continue;
            }
            foreach ($rows as $row) {
                foreach (\preg_split('/\s+/u', (string) ($row['v'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                    if (\str_starts_with($word, $first) && !\in_array($word, $out, true)) {
                        $out[] = $word;
                    }
                }
            }
            if (\count($out) >= $limit) {
                break;
            }
        }

        return \array_slice($out, 0, \max(1, $limit));
    }

    // ─── درونی ───────────────────────────────────────────────────────────

    /**
     * MATCH expression امن: OR کردن tokenهای quote‌شده.
     *
     * @param list<string> $tokens
     */
    private function buildMatch(array $tokens): string
    {
        $parts = [];
        foreach ($tokens as $t) {
            $parts[] = $this->quoteMatch($t);
        }

        return \implode(' OR ', $parts);
    }

    private function quoteMatch(string $term): string
    {
        return '"' . \str_replace('"', '""', $term) . '"';
    }

    /**
     * @param array<string, mixed>   $filters
     * @param array<string, mixed>   $cols
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFilters(string $index, array $filters, array $cols): array
    {
        if ($filters === []) {
            return ['', []];
        }

        $allowed = [...\array_keys((array) $cols['searchable']), ...(array) $cols['extra']];
        $sql = [];
        $bindings = [];

        foreach ($filters as $field => $condition) {
            $field = (string) $field;
            if (!\in_array($field, $allowed, true)) {
                throw SearchException::fieldNotAllowed($field, $index);
            }
            $ident = $this->quoteIdent($field);

            if (\is_array($condition)) {
                foreach ($condition as $op => $operand) {
                    switch ((string) $op) {
                        case 'eq':
                            $sql[] = $ident . ' = ?';
                            $bindings[] = $operand;
                            break;
                        case 'ne':
                            $sql[] = '(' . $ident . ' IS NOT NULL AND ' . $ident . ' != ?)';
                            $bindings[] = $operand;
                            break;
                        case 'lt':
                        case 'lte':
                        case 'gt':
                        case 'gte':
                            $symbol = match ((string) $op) {
                                'lt' => '<', 'lte' => '<=', 'gt' => '>', default => '>=',
                            };
                            $sql[] = 'CAST(' . $ident . ' AS REAL) ' . $symbol . ' ?';
                            $bindings[] = (float) $operand;
                            break;
                        case 'in':
                            $list = \is_array($operand) ? $operand : [$operand];
                            if ($list === []) {
                                $sql[] = '1 = 0';
                                break;
                            }
                            $sql[] = $ident . ' IN (' . \implode(', ', \array_fill(0, \count($list), '?')) . ')';
                            foreach ($list as $item) {
                                $bindings[] = $item;
                            }
                            break;
                        case 'nin':
                            $list = \is_array($operand) ? $operand : [$operand];
                            if ($list === []) {
                                break;
                            }
                            $sql[] = '(' . $ident . ' IS NULL OR ' . $ident . ' NOT IN (' . \implode(', ', \array_fill(0, \count($list), '?')) . '))';
                            foreach ($list as $item) {
                                $bindings[] = $item;
                            }
                            break;
                        default:
                            throw SearchException::for('search.unknown_filter_op', ['op' => (string) $op]);
                    }
                }
            } else {
                $sql[] = $ident . ' = ?';
                $bindings[] = $condition;
            }
        }

        return [$sql === [] ? '' : '(' . \implode(' AND ', $sql) . ')', $bindings];
    }

    /**
     * @param array<string, mixed> $cols
     * @param array<string, string>|list<string> $sort
     */
    private function buildOrder(string $index, array $sort, array $cols): string
    {
        if ($sort === []) {
            return 'rank ASC';   // bm25: منفی‌تر (بهتر) اول
        }

        $allowed = [...\array_keys((array) $cols['searchable']), ...(array) $cols['extra']];
        $parts = [];
        foreach ($sort as $key => $dir) {
            $field = \is_int($key) ? (string) $dir : (string) $key;
            $direction = \is_int($key) ? 'asc' : (\mb_strtolower((string) $dir) === 'desc' ? 'desc' : 'asc');
            if ($field === 'score') {
                $parts[] = 'rank ' . ($direction === 'desc' ? 'DESC' : 'ASC');
                continue;
            }
            if (!\in_array($field, $allowed, true)) {
                throw SearchException::fieldNotAllowed($field, $index);
            }
            $cast = \in_array($field, (array) $cols['numeric'], true) ? 'CAST(' . $this->quoteIdent($field) . ' AS REAL)' : $this->quoteIdent($field);
            $parts[] = $cast . ' ' . ($direction === 'desc' ? 'DESC' : 'ASC');
        }
        $parts[] = 'rank ASC';

        return \implode(', ', $parts);
    }

    /**
     * @param list<string>       $bindings
     * @param list<string>       $facetFields
     * @param array<string, mixed> $cols
     *
     * @return array<string, list<array{value: mixed, count: int}>>
     */
    private function computeFacets(string $table, string $match, array $bindings, string $whereSql, array $facetFields, array $cols): array
    {
        if ($facetFields === []) {
            return [];
        }
        $maxFacets = (int) ($this->config['max_facets'] ?? 50);
        $facets = [];
        foreach ($facetFields as $field) {
            $field = (string) $field;
            if (!\in_array($field, (array) $cols['extra'], true)) {
                continue;   // 🔒 فقط فیلدهای filterable
            }
            $rows = $this->connection->select(
                'SELECT ' . $this->quoteIdent($field) . ' AS v, COUNT(*) AS n FROM ' . $table
                . ' WHERE ' . $whereSql . ' AND ' . $this->quoteIdent($field) . ' IS NOT NULL'
                . ' GROUP BY ' . $this->quoteIdent($field) . ' ORDER BY n DESC LIMIT ' . $maxFacets,
                [$match, ...$bindings],
            );
            $list = [];
            foreach ($rows as $row) {
                $v = $row['v'];
                $list[] = ['value' => \is_numeric($v) ? $v + 0 : (string) $v, 'count' => (int) $row['n']];
            }
            $facets[$field] = $list;
        }

        return $facets;
    }

    /** @return list<string> */
    private function suggestForEmpty(string $index, string $query): array
    {
        return \trim($query) === '' ? [] : $this->suggest($index, $query, 3);
    }

    /**
     * @return array{searchable: array<string, float>, extra: list<string>, numeric: list<string>}
     */
    private function columnsFor(string $index): array
    {
        $indexes = (array) ($this->config['indexes'] ?? []);
        $indexConfig = (array) ($indexes[$index] ?? []);
        $searchable = (array) ($indexConfig['searchable'] ?? ($indexConfig['fields'] ?? []));
        $parsed = [];
        foreach ($searchable as $entry) {
            $entry = (string) $entry;
            if (\str_contains($entry, '^')) {
                [$name, $boost] = \explode('^', $entry, 2);
                $parsed[$this->safeIdent((string) $name)] = (float) $boost > 0 ? (float) $boost : 1.0;
            } else {
                $parsed[$this->safeIdent($entry)] = 1.0;
            }
        }

        $extra = [];
        foreach ((array) ($indexConfig['filterable'] ?? []) as $f) {
            $f = $this->safeIdent((string) $f);
            if (!isset($parsed[$f]) && !\in_array($f, $extra, true)) {
                $extra[] = $f;
            }
        }
        foreach ((array) ($indexConfig['sortable'] ?? []) as $f) {
            $f = $this->safeIdent((string) $f);
            if (!isset($parsed[$f]) && !\in_array($f, $extra, true)) {
                $extra[] = $f;
            }
        }

        $numeric = [];
        foreach ((array) ($indexConfig['numeric'] ?? []) as $f) {
            $numeric[] = $this->safeIdent((string) $f);
        }

        return ['searchable' => $parsed, 'extra' => $extra, 'numeric' => $numeric];
    }

    private function safeIdent(string $name): string
    {
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw SearchException::fieldNotAllowed($name, '(column)');
        }

        return $name;
    }

    private function quoteIdent(string $name): string
    {
        return '"' . \str_replace('"', '""', $this->safeIdent($name)) . '"';
    }

    private function table(string $index): string
    {
        $prefix = (string) ($this->config['sqlite']['prefix'] ?? 'tvn_fts_');

        return '"' . \str_replace('"', '', $prefix) . $index . '"';
    }

    private function validate(string $index): void
    {
        if (\preg_match('/^[A-Za-z0-9_-]{1,64}$/', $index) !== 1) {
            throw SearchException::invalidIndexName($index);
        }
    }

    private function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_array($value)) {
            $parts = [];
            foreach ($value as $v) {
                if (\is_scalar($v)) {
                    $parts[] = (string) $v;
                }
            }

            return \implode(' ', $parts);
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }
}
