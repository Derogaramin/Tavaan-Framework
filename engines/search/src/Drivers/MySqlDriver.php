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
 * MySqlDriver — FULLTEXT با MATCH…AGAINST (فارسی: ngram اختیاری).
 *
 * ```sql
 * CREATE TABLE tvn_fts_{index} (
 *   doc_id VARCHAR(190) PRIMARY KEY,
 *   {searchable...} TEXT,
 *   {extra...},
 *   payload LONGTEXT,
 *   FULLTEXT KEY ft_{index} ({searchable...}) [WITH PARSER ngram]
 * ) ENGINE=InnoDB
 * ```
 *
 * 💡 برای فارسی، ایندکس native معمولاً بهتر از ngram است (DOC-052 §6).
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class MySqlDriver implements SearchDriverInterface
{
    /**
     * @param array<string, mixed> $config کلید search در config
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly array $config = [],
    ) {
    }

    public function createIndex(string $index): void
    {
        $this->validate($index);
        $cols = $this->columnsFor($index);

        $defs = ['doc_id VARCHAR(190) NOT NULL PRIMARY KEY'];
        foreach (\array_keys($cols['searchable']) as $f) {
            $defs[] = $this->quoteIdent($f) . ' TEXT';
        }
        foreach ($cols['extra'] as $f) {
            $defs[] = $this->quoteIdent($f) . ' ' . (\in_array($f, $cols['numeric'], true) ? 'DOUBLE' : 'VARCHAR(190)');
        }
        $defs[] = 'payload LONGTEXT';

        if ($cols['searchable'] !== []) {
            $ftCols = \implode(', ', \array_map($this->quoteIdent(...), \array_keys($cols['searchable'])));
            $ngram = (bool) ($this->config['mysql']['ngram'] ?? false) ? ' WITH PARSER ngram' : '';
            $defs[] = 'FULLTEXT KEY ft_' . $index . ' (' . $ftCols . ')' . $ngram;
        }

        $this->connection->statement(
            'CREATE TABLE IF NOT EXISTS ' . $this->table($index) . ' (' . \implode(', ', $defs) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    public function dropIndex(string $index): void
    {
        $this->validate($index);
        $this->connection->statement('DROP TABLE IF EXISTS ' . $this->table($index));
    }

    public function indexExists(string $index): bool
    {
        $this->validate($index);
        $rows = $this->connection->select('SHOW TABLES LIKE ?', [$this->rawTable($index)]);

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

        foreach ($docs as $docId => $data) {
            $docId = (string) $docId;
            $data = (array) $data;

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

            $updates = [];
            foreach ($columns as $c) {
                if ($c !== 'doc_id') {
                    $updates[] = $this->quoteIdent($c) . ' = VALUES(' . $this->quoteIdent($c) . ')';
                }
            }

            $placeholders = \implode(', ', \array_fill(0, \count($columns), '?'));
            $this->connection->statement(
                'INSERT INTO ' . $this->table($index) . ' (' . \implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
                . ($updates === [] ? '' : ' ON DUPLICATE KEY UPDATE ' . \implode(', ', $updates)),
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

        $page = \max(1, (int) ($options['page'] ?? 1));
        $perPage = \max(1, \min((int) ($options['per_page'] ?? 20), (int) ($this->config['max_per_page'] ?? 100)));

        if ($tokens === []) {
            return new SearchResult([], 0, [], [], $page, $perPage, (\hrtime(true) - $started) / 1e6);
        }

        $cols = $this->columnsFor($index);
        $table = $this->table($index);
        $ftCols = \implode(', ', \array_map($this->quoteIdent(...), \array_keys($cols['searchable'])));
        $against = Persian::normalize(\implode(' ', $tokens));

        [$filterSql, $bindings] = $this->buildFilters($index, (array) ($options['filters'] ?? []), $cols);
        $where = 'MATCH(' . $ftCols . ') AGAINST (? IN NATURAL LANGUAGE MODE) > 0'
            . ($filterSql === '' ? '' : ' AND ' . $filterSql);

        $countRows = $this->connection->select(
            'SELECT COUNT(*) AS n FROM ' . $table . ' WHERE ' . $where,
            [$against, ...$bindings],
        );
        $total = (int) ($countRows[0]['n'] ?? 0);

        $orderSql = $this->buildOrder($index, (array) ($options['sort'] ?? []), $cols);
        $rows = $this->connection->select(
            'SELECT doc_id, payload, MATCH(' . $ftCols . ') AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance'
            . ' FROM ' . $table . ' WHERE ' . $where . ' ORDER BY ' . $orderSql . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            [$against, $against, ...$bindings],
        );

        $facets = $this->computeFacets($table, $against, $bindings, $where, (array) ($options['facets'] ?? []), $cols);

        $highlightFields = (array) ($options['highlight'] ?? []);
        $hits = [];
        foreach ($rows as $row) {
            $payload = \json_decode((string) ($row['payload'] ?? '{}'), true) ?: [];
            $hit = [
                'id' => (string) $row['doc_id'],
                'score' => \round((float) ($row['relevance'] ?? 0), 6),
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

        return new SearchResult(
            hits: $hits,
            total: $total,
            facets: $facets,
            suggestions: $total === 0 ? $this->suggest($index, $query, 3) : [],
            page: $page,
            perPage: $perPage,
            tookMs: (\hrtime(true) - $started) / 1e6,
        );
    }

    public function suggest(string $index, string $query, int $limit = 5): array
    {
        $this->validate($index);
        $normalized = \mb_strtolower(Persian::normalize($query));
        $clean = \trim((string) (\preg_replace('/[^\p{L}\p{N}\s]+/u', '', $normalized) ?? ''));
        $first = (string) (\preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY)[0] ?? '');
        if ($first === '') {
            return [];
        }

        $cols = $this->columnsFor($index);
        $out = [];
        foreach (\array_keys($cols['searchable']) as $field) {
            $rows = $this->connection->select(
                'SELECT ' . $this->quoteIdent($field) . ' AS v FROM ' . $this->table($index)
                . ' WHERE ' . $this->quoteIdent($field) . ' LIKE ? LIMIT ' . \max(1, $limit) * 2,
                [$first . '%'],
            );
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
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $cols
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
            $numeric = \in_array($field, (array) $cols['numeric'], true);
            $casted = $numeric ? $ident : 'CAST(' . $ident . ' AS DOUBLE)';

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
                            $sql[] = $casted . ' ' . $symbol . ' ?';
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
     * @param array<string, string>|list<string> $sort
     * @param array<string, mixed>               $cols
     */
    private function buildOrder(string $index, array $sort, array $cols): string
    {
        if ($sort === []) {
            return 'relevance DESC, doc_id ASC';
        }

        $allowed = [...\array_keys((array) $cols['searchable']), ...(array) $cols['extra']];
        $parts = [];
        foreach ($sort as $key => $dir) {
            $field = \is_int($key) ? (string) $dir : (string) $key;
            $direction = \is_int($key) ? 'ASC' : (\mb_strtolower((string) $dir) === 'desc' ? 'DESC' : 'ASC');
            if ($field === 'score') {
                $parts[] = 'relevance ' . $direction;
                continue;
            }
            if (!\in_array($field, $allowed, true)) {
                throw SearchException::fieldNotAllowed($field, $index);
            }
            $parts[] = $this->quoteIdent($field) . ' ' . $direction;
        }
        $parts[] = 'relevance DESC';

        return \implode(', ', $parts);
    }

    /**
     * @param list<mixed>          $bindings
     * @param list<string>         $facetFields
     * @param array<string, mixed> $cols
     *
     * @return array<string, list<array{value: mixed, count: int}>>
     */
    private function computeFacets(string $table, string $against, array $bindings, string $where, array $facetFields, array $cols): array
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
                . ' WHERE ' . $where . ' AND ' . $this->quoteIdent($field) . ' IS NOT NULL'
                . ' GROUP BY ' . $this->quoteIdent($field) . ' ORDER BY n DESC LIMIT ' . $maxFacets,
                [$against, ...$bindings],
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
        foreach ([...(array) ($indexConfig['filterable'] ?? []), ...(array) ($indexConfig['sortable'] ?? [])] as $f) {
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
        return '`' . \str_replace('`', '', $this->safeIdent($name)) . '`';
    }

    private function rawTable(string $index): string
    {
        $prefix = (string) ($this->config['mysql']['prefix'] ?? 'tvn_fts_');

        return \preg_replace('/[^A-Za-z0-9_]/', '', $prefix) . $index;
    }

    private function table(string $index): string
    {
        return '`' . $this->rawTable($index) . '`';
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
