<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database;

use Tavaan\Core\Contracts\Database\PaginatorInterface;
use Tavaan\Core\Contracts\Database\QueryBuilderInterface;
use Tavaan\Engine\Database\Exceptions\MixedBindingException;
use Tavaan\Engine\Database\Pagination\CursorPaginator;
use Tavaan\Engine\Database\Pagination\Paginator;

/**
 * تَوان | Tavaan — Database
 * QueryBuilder — Builder سیال SELECT/INSERT/UPDATE/DELETE (DOC-047 §4).
 *
 * 🔒 همهٔ مقادیر کاربر → Binding (Prepared Statement ۱۰۰٪؛ بدون الحاق رشته).
 * 🔒 identifierها فقط از راه Grammar::wrap پوشش داده می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class QueryBuilder implements QueryBuilderInterface
{
    /** ترتیب ادغام bindings (مطابق ترتیب اجزا در SQL) */
    private const BINDING_ORDER = ['with', 'select', 'from', 'join', 'where', 'group', 'having', 'union', 'order'];

    /** @var list<string|Expression> */
    private array $columns = ['*'];

    private bool $distinct = false;

    /** @var array{table: string, as: string|null}|null */
    private ?array $from = null;

    /** @var list<array<string, mixed>> */
    private array $joins = [];

    /** @var list<array<string, mixed>> */
    private array $wheres = [];

    /** @var list<string|Expression> */
    private array $groups = [];

    /** @var list<array<string, mixed>> */
    private array $havings = [];

    /** @var list<array{column: string|Expression, direction: string}|Expression> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    private ?string $lock = null;

    /** @var list<array{sql: string, all: bool}> */
    private array $unions = [];

    /** @var list<array{name: string, sql: string}> */
    private array $ctes = [];

    /** @var array{function: string, columns: list<string>}|null */
    private ?array $aggregate = null;

    /** @var array<string, list<mixed>> */
    private array $bindings = [
        'with' => [], 'select' => [], 'from' => [], 'join' => [], 'where' => [],
        'group' => [], 'having' => [], 'union' => [], 'order' => [],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    // ─── اجزای پایه ────────────────────────────────────────────────────────

    public function table(string $table, ?string $as = null): static
    {
        return $this->from($table, $as);
    }

    public function from(string $table, ?string $as = null): static
    {
        $this->from = ['table' => $table, 'as' => $as];

        return $this;
    }

    public function select(string ...$columns): static
    {
        $this->columns = $columns === [] ? ['*'] : \array_values($columns);

        return $this;
    }

    /** @param array<int|string, mixed> $bindings */
    public function selectRaw(string $expression, array $bindings = []): static
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }
        $this->columns[] = new Expression($expression);
        $this->addBinding($bindings, 'select');

        return $this;
    }

    public function selectSub(QueryBuilderInterface|\Closure|string $query, string $as): static
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }
        [$sql, $bindings] = $this->parseSubQuery($query);
        $this->columns[] = new Expression('(' . $sql . ') as ' . $this->grammar()->wrap($as));
        $this->addBinding($bindings, 'select');

        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;

        return $this;
    }

    // ─── JOIN ─────────────────────────────────────────────────────────────

    public function join(string $table, \Closure|string $first, ?string $op = null, ?string $second = null, string $type = 'inner'): static
    {
        $type = \strtolower($type);
        if (!\in_array($type, ['inner', 'left', 'right', 'cross', 'full'], true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown join type [%s].', $type));
        }

        if ($first instanceof \Closure) {
            $clause = new self($this->connection);
            $first($clause);
            $this->joins[] = [
                'type' => $type, 'table' => $table, 'as' => null,
                'wheres' => $clause->wheres,
            ];
            $this->addBinding($clause->getRawBindings()['where'] ?? [], 'join');
        } elseif ($second !== null) {
            $this->joins[] = [
                'type' => $type, 'table' => $table, 'as' => null,
                'wheres' => [['type' => 'Column', 'first' => $first, 'operator' => $op ?? '=', 'second' => $second, 'boolean' => 'and']],
            ];
        } else {
            throw new \InvalidArgumentException('join() requires a Closure or (first, operator, second).');
        }

        return $this;
    }

    public function leftJoin(string $table, \Closure|string $first, ?string $op = null, ?string $second = null): static
    {
        return $this->join($table, $first, $op, $second, 'left');
    }

    public function rightJoin(string $table, \Closure|string $first, ?string $op = null, ?string $second = null): static
    {
        return $this->join($table, $first, $op, $second, 'right');
    }

    public function crossJoin(string $table, \Closure|string|null $first = null, ?string $op = null, ?string $second = null): static
    {
        if ($first === null) {
            $this->joins[] = ['type' => 'cross', 'table' => $table, 'as' => null, 'wheres' => []];

            return $this;
        }

        return $this->join($table, $first, $op, $second, 'cross');
    }

    /** مقایسهٔ ستون-به-ستون (مخصوص on-clause در join). */
    public function on(string $first, string $op, string $second, string $boolean = 'and'): static
    {
        return $this->whereColumn($first, $op, $second, $boolean);
    }

    public function whereColumn(string $first, string $op, string $second, string $boolean = 'and'): static
    {
        $this->wheres[] = ['type' => 'Column', 'first' => $first, 'operator' => $op, 'second' => $second, 'boolean' => $this->normalizeBoolean($boolean)];

        return $this;
    }

    // ─── WHERE ────────────────────────────────────────────────────────────

    public function where(string|\Closure|QueryBuilderInterface $column, mixed $op = null, mixed $value = null, string $boolean = 'and'): static
    {
        $boolean = $this->normalizeBoolean($boolean);

        if ($column instanceof \Closure) {
            return $this->whereNested($column, $boolean);
        }
        if ($column instanceof QueryBuilderInterface) {
            if ($column instanceof self) {
                $this->wheres = \array_merge($this->wheres, $column->wheres);
                $this->addBinding($column->getRawBindings()['where'] ?? [], 'where');
            } else {
                [$sql, $bindings] = $this->parseSubQuery($column);
                $this->wheres[] = ['type' => 'Exists', 'sql' => $sql, 'not' => false, 'boolean' => $boolean];
                $this->addBinding($bindings, 'where');
            }

            return $this;
        }

        // where('col', $value) → op '='
        if (\func_num_args() === 2) {
            $value = $op;
            $op = '=';
        }

        $op = $this->normalizeOperator((string) $op);

        if ($value instanceof \Closure || $value instanceof QueryBuilderInterface) {
            [$sql, $bindings] = $this->parseSubQuery($value);
            $this->wheres[] = ['type' => 'Sub', 'column' => $column, 'operator' => $op, 'sql' => $sql, 'boolean' => $boolean];
            $this->addBinding($bindings, 'where');

            return $this;
        }

        $this->wheres[] = ['type' => 'Basic', 'column' => $column, 'operator' => $op, 'value' => $value, 'boolean' => $boolean];
        $this->addBinding([$this->normalizeBindingValue($value)], 'where');

        return $this;
    }

    public function orWhere(string|\Closure|QueryBuilderInterface $column, mixed $op = null, mixed $value = null): static
    {
        $args = \func_get_args();

        if (\count($args) === 2 && !$column instanceof \Closure && !$column instanceof self) {
            // orWhere('col', $value) → orWhere('col', '=', $value)
            return $this->where($column, '=', $op, 'or');
        }

        return $this->where($column, $op, $value, 'or');
    }

    private function whereNested(\Closure $callback, string $boolean): static
    {
        $query = new self($this->connection);
        $callback($query);

        if ($query->wheres !== []) {
            $this->wheres[] = ['type' => 'Nested', 'wheres' => $query->wheres, 'boolean' => $boolean];
            $this->addBinding($query->getRawBindings()['where'] ?? [], 'where');
        }

        return $this;
    }

    /** @param array<int, mixed>|self|\Closure $values */
    public function whereIn(string $column, array|QueryBuilderInterface|\Closure $values, string $boolean = 'and', bool $not = false): static
    {
        $boolean = $this->normalizeBoolean($boolean);

        if ($values instanceof \Closure || $values instanceof QueryBuilderInterface) {
            [$sql, $bindings] = $this->parseSubQuery($values);
            $this->wheres[] = ['type' => 'In', 'column' => $column, 'placeholder' => $sql, 'not' => $not, 'boolean' => $boolean];
            $this->addBinding($bindings, 'where');

            return $this;
        }

        $values = \array_values($values);
        if ($values === []) {
            // IN () نامعتبر است → شرط قطعی false/true
            $this->wheres[] = ['type' => 'Raw', 'sql' => $not ? '1 = 1' : '0 = 1', 'boolean' => $boolean];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'In', 'column' => $column, 'not' => $not, 'boolean' => $boolean,
            'placeholder' => \implode(', ', \array_fill(0, \count($values), '?')),
        ];
        $this->addBinding(\array_map(fn ($v): mixed => $this->normalizeBindingValue($v), $values), 'where');

        return $this;
    }

    /** @param array<int, mixed>|self|\Closure $values */
    public function whereNotIn(string $column, array|QueryBuilderInterface|\Closure $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = ['type' => 'Null', 'column' => $column, 'not' => $not, 'boolean' => $this->normalizeBoolean($boolean)];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    /** @param array{0: mixed, 1: mixed} $values */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        if (\count($values) !== 2) {
            throw new \InvalidArgumentException('whereBetween() expects exactly 2 values.');
        }

        $this->wheres[] = ['type' => 'Between', 'column' => $column, 'not' => $not, 'boolean' => $this->normalizeBoolean($boolean)];
        $this->addBinding([
            $this->normalizeBindingValue(\array_values($values)[0]),
            $this->normalizeBindingValue(\array_values($values)[1]),
        ], 'where');

        return $this;
    }

    /** @param array{0: mixed, 1: mixed} $values */
    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function whereExists(\Closure|QueryBuilderInterface $callback, string $boolean = 'and', bool $not = false): static
    {
        [$sql, $bindings] = $this->parseSubQuery($callback);
        $this->wheres[] = ['type' => 'Exists', 'sql' => $sql, 'not' => $not, 'boolean' => $this->normalizeBoolean($boolean)];
        $this->addBinding($bindings, 'where');

        return $this;
    }

    public function whereNotExists(\Closure|QueryBuilderInterface $callback, string $boolean = 'and'): static
    {
        return $this->whereExists($callback, $boolean, true);
    }

    /** @param array<int|string, mixed> $bindings */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $this->wheres[] = ['type' => 'Raw', 'sql' => $sql, 'boolean' => $this->normalizeBoolean($boolean)];
        $this->addBinding($bindings, 'where');

        return $this;
    }

    // ─── GROUP / HAVING / ORDER / LIMIT ───────────────────────────────────

    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->groups[] = $column;
        }

        return $this;
    }

    /** @param array<int|string, mixed> $bindings */
    public function groupByRaw(string $sql, array $bindings = []): static
    {
        $this->groups[] = new Expression($sql);
        $this->addBinding($bindings, 'group');

        return $this;
    }

    public function having(string $column, mixed $op = null, mixed $value = null, string $boolean = 'and'): static
    {
        if (\func_num_args() === 2) {
            $value = $op;
            $op = '=';
        }

        $this->havings[] = ['type' => 'Basic', 'column' => $column, 'operator' => $this->normalizeOperator((string) $op), 'boolean' => $this->normalizeBoolean($boolean)];
        $this->addBinding([$this->normalizeBindingValue($value)], 'having');

        return $this;
    }

    public function orHaving(string $column, mixed $op = null, mixed $value = null): static
    {
        $args = \func_get_args();

        if (\count($args) === 2) {
            return $this->having($column, '=', $op, 'or');
        }

        return $this->having($column, $op, $value, 'or');
    }

    /** @param array<int|string, mixed> $bindings */
    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $this->havings[] = ['type' => 'Raw', 'sql' => $sql, 'boolean' => $this->normalizeBoolean($boolean)];
        $this->addBinding($bindings, 'having');

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $direction = \strtolower(\trim($direction));
        if (!\in_array($direction, ['asc', 'desc'], true)) {
            throw new Exceptions\UnsafeQueryException(context: ['direction' => $direction]);
        }

        $this->orders[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    /** @param array<int|string, mixed> $bindings */
    public function orderByRaw(string $sql, array $bindings = []): static
    {
        $this->orders[] = new Expression($sql);
        $this->addBinding($bindings, 'order');

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit >= 0 ? $limit : null;

        return $this;
    }

    public function take(int $limit): static
    {
        return $this->limit($limit);
    }

    public function offset(int $offset): static
    {
        $this->offset = \max(0, $offset);

        return $this;
    }

    public function skip(int $offset): static
    {
        return $this->offset($offset);
    }

    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->limit($perPage)->offset(($page - 1) * $perPage);
    }

    public function forUpdate(): static
    {
        $this->lock = 'forUpdate';

        return $this;
    }

    /**
     * قفل ردیف با SKIP LOCKED — ردیف‌های قفل‌شده توسط دیگران رد می‌شوند.
     *
     * 🔒 MySQL 8.0+ / PostgreSQL 9.5+؛ در SQLite clause خالی است
     * (کل DB در تراکنش قفل می‌شود — اتمیک بودن همچنان برقرار است).
     * کاربرد: Pop اتمیک در Queue Workerهای موازی (DOC-061 §6).
     */
    public function forUpdateSkipLocked(): static
    {
        $this->lock = 'forUpdateSkipLocked';

        return $this;
    }

    /** نام مستعار forUpdateSkipLocked (DOC-061 §6). */
    public function lockForUpdateSkipLocked(): static
    {
        return $this->forUpdateSkipLocked();
    }

    public function sharedLock(): static
    {
        $this->lock = 'sharedLock';

        return $this;
    }

    // ─── UNION / CTE ──────────────────────────────────────────────────────

    public function union(QueryBuilderInterface|\Closure $query, bool $all = false): static
    {
        [$sql, $bindings] = $this->parseSubQuery($query);
        $this->unions[] = ['sql' => $sql, 'all' => $all];
        $this->addBinding($bindings, 'union');

        return $this;
    }

    public function unionAll(QueryBuilderInterface|\Closure $query): static
    {
        return $this->union($query, true);
    }

    public function withCte(string $name, \Closure|QueryBuilderInterface $query): static
    {
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new Exceptions\UnsafeQueryException(context: ['cte' => $name], hint: 'نام CTE فقط حرف/عدد/underscore.');
        }

        [$sql, $bindings] = $this->parseSubQuery($query);
        $this->ctes[] = ['name' => $name, 'sql' => $sql];
        $this->addBinding($bindings, 'with');

        return $this;
    }

    // ─── خروجی SQL ────────────────────────────────────────────────────────

    public function toSql(): string
    {
        return $this->grammar()->compileSelect($this->components());
    }

    /** @return array<int|string, mixed> */
    public function getBindings(): array
    {
        $merged = [];
        foreach (self::BINDING_ORDER as $type) {
            foreach ($this->bindings[$type] as $key => $value) {
                if (\is_int($key)) {
                    $merged[] = $value;
                } else {
                    $merged[$key] = $value;
                }
            }
        }

        $this->assertNotMixed($merged);

        return $merged;
    }

    /** @return array<string, list<mixed>> */
    public function getRawBindings(): array
    {
        return $this->bindings;
    }

    /** @return array<string, mixed> اجزای نرمال‌شده برای Grammar */
    public function components(): array
    {
        return [
            'ctes' => $this->ctes,
            'aggregate' => $this->aggregate,
            'columns' => $this->columns,
            'distinct' => $this->distinct,
            'from' => $this->from,
            'joins' => $this->joins,
            'wheres' => $this->prepareWheresForGrammar($this->wheres),
            'groups' => $this->groups,
            'havings' => $this->havings,
            'unions' => $this->unions,
            'orders' => $this->orders,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'lock' => $this->lock,
        ];
    }

    /**
     * تبدیل where نوع Sub به شکل قابل کامپایل (Raw با placeholder).
     *
     * @param list<array<string, mixed>> $wheres
     *
     * @return list<array<string, mixed>>
     */
    private function prepareWheresForGrammar(array $wheres): array
    {
        $out = [];
        foreach ($wheres as $where) {
            if (($where['type'] ?? '') === 'Sub') {
                $out[] = [
                    'type' => 'Raw',
                    'boolean' => $where['boolean'],
                    'sql' => $this->grammar()->wrap($where['column']) . ' ' . $where['operator'] . ' (' . $where['sql'] . ')',
                ];
                continue;
            }
            if (($where['type'] ?? '') === 'Nested') {
                $where['wheres'] = $this->prepareWheresForGrammar($where['wheres']);
            }
            $out[] = $where;
        }

        return $out;
    }

    // ─── خواندن ───────────────────────────────────────────────────────────

    /**
     * @param list<string> $columns
     *
     * @return list<array<string, mixed>>
     */
    public function get(array $columns = ['*']): array
    {
        $builder = $columns === ['*'] ? $this : (clone $this)->select(...$columns);

        return $this->connection->select($builder->toSql(), $builder->getBindings(), $builder->usesWriteConnection());
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function cursor(): \Generator
    {
        yield from $this->connection->cursor($this->toSql(), $this->getBindings(), $this->usesWriteConnection());
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>|null
     */
    public function first(array $columns = ['*']): ?array
    {
        $rows = $this->limit(1)->get($columns);

        return $rows[0] ?? null;
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>|null
     */
    public function find(int|string $id, array $columns = ['*']): ?array
    {
        return $this->where('id', $id)->first($columns);
    }

    public function value(string $column): mixed
    {
        $row = $this->first([$column]);

        return $row === null ? null : ($row[$column] ?? null);
    }

    /** @return array<int|string, mixed> */
    public function pluck(string $column, ?string $key = null): array
    {
        $columns = $key === null ? [$column] : [$column, $key];
        $rows = $this->get($columns);

        $out = [];
        foreach ($rows as $row) {
            if ($key === null) {
                $out[] = $row[$column] ?? null;
            } else {
                $out[(string) ($row[$key] ?? '')] = $row[$column] ?? null;
            }
        }

        return $out;
    }

    public function count(string $column = '*'): int
    {
        $result = $this->aggregateFunction('count', [$column]);

        return (int) ($result ?? 0);
    }

    public function sum(string $column): int|float|null
    {
        return $this->numericAggregate($this->aggregateFunction('sum', [$column]));
    }

    public function avg(string $column): int|float|null
    {
        return $this->numericAggregate($this->aggregateFunction('avg', [$column]));
    }

    public function min(string $column): int|float|null
    {
        return $this->numericAggregate($this->aggregateFunction('min', [$column]));
    }

    public function max(string $column): int|float|null
    {
        return $this->numericAggregate($this->aggregateFunction('max', [$column]));
    }

    public function exists(): bool
    {
        $clone = clone $this;
        $clone->columns = ['1'];
        $clone->bindings['select'] = [];
        $clone->orders = [];
        $clone->bindings['order'] = [];
        $clone->limit = 1;

        return $clone->connection->select($clone->toSql(), $clone->getBindings(), $clone->usesWriteConnection()) !== [];
    }

    /** @param list<string> $columns */
    private function aggregateFunction(string $function, array $columns): mixed
    {
        $clone = clone $this;
        $clone->aggregate = ['function' => $function, 'columns' => $columns];
        $clone->columns = ['*'];
        $clone->bindings['select'] = [];
        $clone->orders = [];
        $clone->bindings['order'] = [];
        $clone->limit = null;
        $clone->offset = null;

        $row = $clone->connection->selectOne($clone->toSql(), $clone->getBindings(), $clone->usesWriteConnection());
        if ($row === null) {
            return null;
        }

        return $row['aggregate'] ?? \array_values($row)[0] ?? null;
    }

    private function numericAggregate(mixed $value): int|float|null
    {
        if ($value === null || !\is_numeric($value)) {
            return null;
        }

        $num = $value + 0;

        return \is_int($num) ? $num : (\is_float($num) ? $num : null);
    }

    // ─── نوشتن ────────────────────────────────────────────────────────────

    /** @param array<string, mixed>|list<array<string, mixed>> $values */
    public function insert(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        $rows = $this->normalizeRows($values);
        $sql = $this->grammar()->compileInsert($this->tableOrFail(), rows: $rows);

        return $this->connection->insert($sql, $this->flattenRows($rows));
    }

    /** @param array<string, mixed> $values */
    public function insertGetId(array $values, ?string $sequence = null): int|string
    {
        $table = $this->tableOrFail();
        $grammar = $this->grammar();

        if ($grammar instanceof Grammars\PostgresGrammar) {
            $sequence ??= 'id';
            $sql = $grammar->compileInsertGetId($table, \array_keys($values), $sequence);
            $row = $this->connection->selectOne($sql, $this->normalizeBindingValues($values), true);

            return $row === null ? 0 : (string) ($row[$sequence] ?? '');
        }

        $sql = $grammar->compileInsert($table, $values);
        $this->connection->insert($sql, $this->normalizeBindingValues($values));

        return $this->connection->lastInsertId($sequence);
    }

    /** @param list<array<string, mixed>> $values */
    public function insertOrIgnore(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $rows = $this->normalizeRows($values);
        $mode = match ($this->connection->driver()) {
            'mysql' => 'ignore-mysql',
            'pgsql' => 'ignore-pgsql',
            default => 'ignore-sqlite',
        };
        $sql = $this->grammar()->compileInsert($this->tableOrFail(), rows: $rows, mode: $mode);

        return $this->connection->affectingStatement($sql, $this->flattenRows($rows), true);
    }

    /**
     * @param list<array<string, mixed>> $values
     * @param list<string>               $uniqueBy
     * @param list<string>|null          $update
     */
    public function upsert(array $values, array $uniqueBy, ?array $update = null): int
    {
        if ($values === []) {
            return 0;
        }

        $rows = $this->normalizeRows($values);
        $columns = \array_keys($rows[0]);
        $sql = $this->grammar()->compileUpsert($this->tableOrFail(), $columns, $uniqueBy, $update ?? [], \count($rows));

        return $this->connection->affectingStatement($sql, $this->flattenRows($rows), true);
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $sql = $this->grammar()->compileUpdate($this->components(), $values);
        $bindings = \array_merge($this->setBindings($values), $this->getBindings());

        return $this->connection->update($sql, $bindings, $this->usesWriteConnection());
    }

    /** @param array<string, mixed> $extra */
    public function increment(string $column, int|float $amount = 1, array $extra = []): int
    {
        return $this->adjustColumn($column, $amount, $extra, '+');
    }

    /** @param array<string, mixed> $extra */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): int
    {
        return $this->adjustColumn($column, $amount, $extra, '-');
    }

    /** @param array<string, mixed> $extra */
    private function adjustColumn(string $column, int|float $amount, array $extra, string $sign): int
    {
        $wrapped = $this->grammar()->wrap($column);
        $values = \array_merge($extra, [$column => new Expression($wrapped . ' ' . $sign . ' ?', [$amount])]);

        return $this->update($values);
    }

    public function delete(): int
    {
        $sql = $this->grammar()->compileDelete($this->components());

        return $this->connection->delete($sql, $this->getBindings(), $this->usesWriteConnection());
    }

    public function truncate(): void
    {
        $table = $this->tableOrFail();
        $this->connection->statement($this->grammar()->compileTruncate($table));

        if ($this->connection->driver() === 'sqlite') {
            // sqlite_sequence ممکن است هنوز ساخته نشده باشد (بدون AUTOINCREMENT)
            try {
                $name = $this->grammar()->prefix() . $table;
                $this->connection->statement('delete from "sqlite_sequence" where "name" = ?', [$name]);
            } catch (Exceptions\QueryException) {
                // ignore
            }
        }
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        $probe = $this->clone();
        foreach ($attributes as $column => $value) {
            $probe->where((string) $column, $value);
        }

        if ($probe->exists()) {
            if ($values === []) {
                return true;
            }

            $updater = $this->clone();
            foreach ($attributes as $column => $value) {
                $updater->where((string) $column, $value);
            }
            $updater->update($values);

            return true;
        }

        return $this->insert(\array_merge($attributes, $values));
    }

    // ─── صفحه‌بندی / مجموعهٔ بزرگ ─────────────────────────────────────────

    /** @param list<string> $columns */
    public function paginate(int $perPage = 15, int $page = 1, array $columns = ['*']): PaginatorInterface
    {
        $maxPerPage = $this->connection->maxPerPage();
        $perPage = \max(1, \min($perPage, $maxPerPage));
        $page = \max(1, $page);

        $total = $this->countForPagination();
        $items = $this->forPage($page, $perPage)->get($columns);

        return new Paginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            options: Paginator::optionsFromGlobals(),
        );
    }

    private function countForPagination(): int
    {
        $clone = clone $this;
        $clone->orders = [];
        $clone->bindings['order'] = [];
        $clone->limit = null;
        $clone->offset = null;

        if ($clone->groups !== [] || $clone->unions !== []) {
            // COUNT روی گروه/یونیون → زیرکوئری
            $sql = 'select count(*) as ' . $this->grammar()->wrap('aggregate') . ' from (' . $clone->toSql() . ') as ' . $this->grammar()->wrap('count_table');

            $row = $this->connection->selectOne($sql, $clone->getBindings(), $clone->usesWriteConnection());

            return (int) ($row['aggregate'] ?? 0);
        }

        return $clone->count();
    }

    /**
     * Cursor pagination (بدون COUNT — DOC-053 §4).
     *
     * @param list<string> $columns
     */
    public function cursorPaginate(int $perPage = 15, ?string $cursor = null, ?string $cursorKey = 'cursor', array $columns = ['*']): CursorPaginator
    {
        $maxPerPage = $this->connection->maxPerPage();
        $perPage = \max(1, \min($perPage, $maxPerPage));

        $sorts = $this->extractSorts();
        $clone = clone $this;

        if ($cursor !== null && $cursor !== '') {
            $decoded = CursorPaginator::decodeCursor($cursor);
            $clone->applyCursorWhere($sorts, $decoded);
        }

        $items = $clone->limit($perPage + 1)->get($columns);
        $hasMore = \count($items) > $perPage;
        if ($hasMore) {
            \array_pop($items);
        }

        $nextCursor = $hasMore && $items !== [] ? CursorPaginator::encodeCursor($items[\count($items) - 1], $sorts) : null;

        return new CursorPaginator($items, $perPage, $nextCursor, $cursor, $cursorKey);
    }

    /**
     * Keyset pagination (بسیار سریع — DOC-053 §2).
     *
     * @param list<string> $columns
     */
    public function keysetPaginate(int $perPage = 50, int|string|null $afterId = null, string $column = 'id', array $columns = ['*']): CursorPaginator
    {
        $maxPerPage = $this->connection->maxPerPage();
        $perPage = \max(1, \min($perPage, $maxPerPage));

        $clone = (clone $this)->orderBy($column, 'asc');
        if ($afterId !== null) {
            $clone->where($column, '>', $afterId);
        }

        $items = $clone->limit($perPage + 1)->get($columns);
        $hasMore = \count($items) > $perPage;
        if ($hasMore) {
            \array_pop($items);
        }

        $last = $items === [] ? null : $items[\count($items) - 1];
        $nextCursor = $hasMore && $last !== null ? (string) ($last[$column] ?? '') : null;

        return new CursorPaginator($items, $perPage, $nextCursor, $afterId === null ? null : (string) $afterId, $column);
    }

    /**
     * @return list<array{column: string, direction: string}>
     */
    private function extractSorts(): array
    {
        $sorts = [];
        foreach ($this->orders as $order) {
            if ($order instanceof Expression) {
                continue;
            }
            $sorts[] = ['column' => (string) $order['column'], 'direction' => (string) $order['direction']];
        }

        // 🔒 پایداری cursor: ستون یکتا در انتهای مرتب‌سازی
        $hasId = false;
        foreach ($sorts as $sort) {
            if (\str_ends_with($sort['column'], 'id')) {
                $hasId = true;
            }
        }
        if (!$hasId) {
            $sorts[] = ['column' => 'id', 'direction' => 'asc'];
        }

        return $sorts === [] ? [['column' => 'id', 'direction' => 'asc']] : $sorts;
    }

    /**
     * WHERE زنجیره‌ای برای cursor چندستونی: (a,b) < (ca,cb) برای DESC.
     *
     * @param list<array{column: string, direction: string}> $sorts
     * @param array<string, mixed>                           $decoded
     */
    private function applyCursorWhere(array $sorts, array $decoded): void
    {
        $this->where(function (self $query) use ($sorts, $decoded): void {
            $count = \count($sorts);
            for ($i = 0; $i < $count; $i++) {
                $sort = $sorts[$i];
                $value = $decoded[$sort['column']] ?? null;
                if ($value === null) {
                    continue;
                }

                $query->where(function (self $inner) use ($sorts, $decoded, $i, $sort, $value): void {
                    // ستون‌های قبلی با مقدار cursor برابرند
                    for ($j = 0; $j < $i; $j++) {
                        $prev = $sorts[$j];
                        $inner->where((string) $prev['column'], '=', $decoded[$prev['column']] ?? null);
                    }
                    $op = $sort['direction'] === 'desc' ? '<' : '>';
                    $inner->where((string) $sort['column'], $op, $value);
                }, null, null, $i === 0 ? 'and' : 'or');
            }
        });
    }

    /**
     * @param callable(list<array<string, mixed>>): bool|void $callback
     */
    public function chunk(int $size, callable $callback): bool
    {
        $size = \max(1, $size);
        $page = 1;

        do {
            $results = $this->forPage($page, $size)->get();
            $count = \count($results);

            if ($count === 0) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            if ($count < $size) {
                break;
            }

            $page++;
        } while (true);

        return true;
    }

    /** chunk امن بر اساس کلید (بدون OFFSET — بدون تکرار/جاافتادن). */
    public function chunkById(int $size, callable $callback, string $column = 'id'): bool
    {
        $size = \max(1, $size);
        $lastId = null;

        do {
            $clone = (clone $this)->orderBy($column, 'asc')->limit($size);
            if ($lastId !== null) {
                $clone->where($column, '>', $lastId);
            }

            $results = $clone->get();
            if ($results === []) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            $lastRow = $results[\count($results) - 1];
            $lastId = $lastRow[$column] ?? null;

            if (\count($results) < $size || $lastId === null) {
                break;
            }
        } while (true);

        return true;
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function lazy(int $chunkSize = 1000): \Generator
    {
        yield from $this->lazyById($chunkSize);
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function lazyById(int $chunkSize = 1000, string $column = 'id'): \Generator
    {
        $lastId = null;

        do {
            $clone = (clone $this)->orderBy($column, 'asc')->limit($chunkSize);
            if ($lastId !== null) {
                $clone->where($column, '>', $lastId);
            }

            $results = $clone->get();
            foreach ($results as $row) {
                yield $row;
            }

            if (\count($results) < $chunkSize) {
                break;
            }

            $lastId = $results[\count($results) - 1][$column] ?? null;
        } while ($lastId !== null);
    }

    // ─── کلون ─────────────────────────────────────────────────────────────

    public function clone(): static
    {
        return clone $this;
    }

    /**
     * پاک‌سازی wheres (برای eager loading: constraints lazy حذف می‌شوند).
     */
    public function resetWheres(): void
    {
        $this->wheres = [];
        $this->bindings['where'] = [];
    }

    // ─── درونی ────────────────────────────────────────────────────────────

    public function grammar(): Grammars\Grammar
    {
        return $this->connection->grammar();
    }

    private function tableOrFail(): string
    {
        if ($this->from === null) {
            throw new \LogicException('Query builder has no table (call table()/from() first).');
        }

        return $this->from['as'] !== null ? $this->from['table'] . ' as ' . $this->from['as'] : $this->from['table'];
    }

    private function usesWriteConnection(): bool
    {
        return $this->lock !== null;
    }

    private function normalizeBoolean(string $boolean): string
    {
        $boolean = \strtolower($boolean);

        return $boolean === 'or' ? 'or' : 'and';
    }

    private function normalizeOperator(string $op): string
    {
        $op = \strtolower(\trim($op));
        if ($op === '') {
            return '=';
        }
        if (!\in_array($op, Grammars\Grammar::OPERATORS, true)) {
            throw new Exceptions\UnsafeQueryException(
                context: ['operator' => $op],
                hint: 'فقط operatorهای allowlist مجازند (=, <, >, like, …).',
            );
        }

        return $op;
    }

    private function normalizeBindingValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.u');
        }
        if ($value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<mixed>
     */
    private function normalizeBindingValues(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $out[] = $this->normalizeBindingValue($value);
        }

        return $out;
    }

    /**
     * bindings بخش SET (قبل از where) — Expressionها binding خود را دارند.
     *
     * @param array<string, mixed> $values
     *
     * @return list<mixed>
     */
    private function setBindings(array $values): array
    {
        $bindings = [];
        foreach ($values as $column => $value) {
            if ($value instanceof Expression) {
                foreach ($value->bindings as $b) {
                    $bindings[] = $b;
                }
                continue;
            }
            if (\str_contains((string) $column, '->')) {
                $bindings[] = $this->normalizeBindingValue($value);
                continue;
            }
            $bindings[] = $this->normalizeBindingValue($value);
        }

        return $bindings;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    private function addBinding(array $bindings, string $type): void
    {
        if (!isset($this->bindings[$type])) {
            throw new \InvalidArgumentException(\sprintf('Unknown binding type [%s].', $type));
        }

        foreach ($bindings as $key => $value) {
            if (\is_int($key)) {
                $this->bindings[$type][] = $value;
            } else {
                $this->bindings[$type][$key] = $value;
            }
        }
    }

    /** @param array<int|string, mixed> $merged */
    private function assertNotMixed(array $merged): void
    {
        $hasInt = false;
        $hasString = false;
        foreach (\array_keys($merged) as $key) {
            if (\is_int($key)) {
                $hasInt = true;
            } else {
                $hasString = true;
            }
            if ($hasInt && $hasString) {
                throw new MixedBindingException();
            }
        }
    }

    /**
     * Closure/QueryBuilder → (sql, bindings).
     *
     * @return array{0: string, 1: array<int|string, mixed>}
     */
    private function parseSubQuery(QueryBuilderInterface|\Closure|string $query): array
    {
        if (\is_string($query)) {
            return [$query, []];
        }

        if ($query instanceof QueryBuilderInterface) {
            return [$query->toSql(), $query->getBindings()];
        }

        $sub = (new self($this->connection))->tap($query);

        return [$sub->toSql(), $sub->getBindings()];
    }

    private function tap(\Closure $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * نرمال‌سازی insert چندسطری: همهٔ سطرها با ستون‌های یکسان (کسری → null).
     *
     * @param array<string, mixed>|list<array<string, mixed>> $values
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeRows(array $values): array
    {
        if (!isset($values[0]) || !\is_array($values[0])) {
            /** @var array<string, mixed> $values */
            $rows = [$values];
        } else {
            /** @var list<array<string, mixed>> $rows */
            $rows = \array_values($values);
        }

        $columns = [];
        foreach ($rows as $row) {
            foreach (\array_keys($row) as $key) {
                $columns[(string) $key] = true;
            }
        }
        $columns = \array_keys($columns);

        $normalized = [];
        foreach ($rows as $row) {
            $normalizedRow = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $normalizedRow[$column] = $this->normalizeBindingValue($value);
            }
            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<mixed>
     */
    private function flattenRows(array $rows): array
    {
        $bindings = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $bindings[] = $value;
            }
        }

        return $bindings;
    }
}
