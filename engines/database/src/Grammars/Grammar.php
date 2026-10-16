<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Grammars;

use Tavaan\Core\Contracts\Database\GrammarInterface;
use Tavaan\Engine\Database\Exceptions\UnsafeQueryException;
use Tavaan\Engine\Database\Expression;

/**
 * تَوان | Tavaan — Database
 * Grammar — کامپایل SQL از اجزای Builder (پایهٔ اشتراکی سه درایور — DOC-047 §5).
 *
 * 🔒 همهٔ «مقادیر» به `?` تبدیل می‌شوند؛ فقط «شناسه‌ها» wrap می‌شوند.
 * 🔒 operator و direction با allowlist اعتبارسنجی می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
abstract class Grammar implements GrammarInterface
{
    /** operatorهای مجاز (allowlist — جلوگیری از injection) */
    public const OPERATORS = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'not like', 'ilike', 'rlike', 'regexp', 'not regexp',
        'is', 'is not',
    ];

    /** جهت‌های مجاز order */
    public const DIRECTIONS = ['asc', 'desc'];

    public function __construct(protected readonly string $tablePrefix = '')
    {
    }

    public function prefix(): string
    {
        return $this->tablePrefix;
    }

    // ─── شناسه‌ها ──────────────────────────────────────────────────────────

    public function wrap(string|\Stringable $value): string
    {
        if ($value instanceof Expression) {
            $this->assertSafeRaw($value->value, 'expression');

            return $value->value;
        }
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        // alias: `users as u`
        if (\stripos($value, ' as ') !== false) {
            $parts = \preg_split('/\s+as\s+/i', $value, 2) ?: [$value];

            return $this->wrap($parts[0]) . ' as ' . $this->wrapValue($parts[1] ?? '');
        }

        if ($value === '*') {
            return '*';
        }

        // dotted: db.table.column
        $segments = \explode('.', $value);
        $wrapped = [];
        foreach ($segments as $segment) {
            $wrapped[] = $segment === '*' ? '*' : $this->wrapValue($segment);
        }

        return \implode('.', $wrapped);
    }

    public function wrapTable(string $table): string
    {
        if ($table === '') {
            return '';
        }

        // alias روی جدول
        if (\stripos($table, ' as ') !== false) {
            $parts = \preg_split('/\s+as\s+/i', $table, 2) ?: [$table];

            return $this->wrapTable($parts[0]) . ' as ' . $this->wrapValue($parts[1] ?? '');
        }

        $segments = \explode('.', $table);
        if (\count($segments) === 1 && $this->tablePrefix !== '') {
            $segments[0] = $this->tablePrefix . $segments[0];
        }

        $wrapped = [];
        foreach ($segments as $segment) {
            $wrapped[] = $segment === '*' ? '*' : $this->wrapValue($segment);
        }

        return \implode('.', $wrapped);
    }

    public function wrapArray(array $values): array
    {
        return \array_map(fn (string|\Stringable $v): string => $this->wrap($v), $values);
    }

    /** پوشش یک شناسهٔ تک‌بخشی (بدون نقطه) — مخصوص درایور. */
    abstract public function wrapValue(string $value): string;

    /** نام تابع تجمیعی معتبر (allowlist). */
    protected function validateAggregateFunction(string $fn): string
    {
        $normalized = \strtolower(\trim($fn));
        if (!\in_array($normalized, ['count', 'sum', 'avg', 'min', 'max'], true)) {
            throw new UnsafeQueryException(context: ['aggregate' => $fn]);
        }

        return $normalized;
    }

    // ─── SELECT ───────────────────────────────────────────────────────────

    /** @param array<string, mixed> $components */
    public function compileSelect(array $components): string
    {
        $parts = [];
        foreach ([
            $this->compileCtes($components),
            $this->compileColumns($components),
            $this->compileFrom($components),
            $this->compileJoins($components),
            $this->compileWhereSection($components),
            $this->compileGroups($components),
            $this->compileHavings($components),
            $this->compileUnions($components),
            $this->compileOrders($components),
            $this->compileLimitOffset($components),
            $this->compileLock($components),
        ] as $part) {
            if ($part !== '' && $part !== null) {
                $parts[] = $part;
            }
        }

        return \implode(' ', $parts);
    }

    /** @param array<string, mixed> $components */
    protected function compileCtes(array $components): string
    {
        $ctes = $components['ctes'] ?? [];
        if ($ctes === []) {
            return '';
        }

        $parts = [];
        foreach ($ctes as $cte) {
            /** @var array{name: string, sql: string} $cte */
            $parts[] = $this->wrapValue($cte['name']) . ' as (' . $cte['sql'] . ')';
        }

        return 'with ' . \implode(', ', $parts);
    }

    /** @param array<string, mixed> $components */
    protected function compileColumns(array $components): string
    {
        $distinct = ($components['distinct'] ?? false) ? 'distinct ' : '';

        if (isset($components['aggregate'])) {
            /** @var array{function: string, columns: list<string>} $agg */
            $agg = $components['aggregate'];
            $fn = $this->validateAggregateFunction($agg['function']);
            $cols = $agg['columns'] === ['*'] ? '*' : $this->columnize($agg['columns']);
            if (($components['distinct'] ?? false) && $cols !== '*') {
                $cols = 'distinct ' . $cols;
            }

            return 'select ' . $distinct . $fn . '(' . $cols . ') as ' . $this->wrapValue('aggregate');
        }

        /** @var list<string|Expression> $columns */
        $columns = $components['columns'] ?? ['*'];

        return 'select ' . $distinct . $this->columnize($columns);
    }

    /**
     * @param list<string|Expression>|array<int|string, mixed> $columns
     */
    public function columnize(array $columns): string
    {
        return \implode(', ', \array_map(fn (string|Expression $c): string => $this->wrap($c), $columns));
    }

    /** @param array<string, mixed> $components */
    protected function compileFrom(array $components): string
    {
        /** @var array{table: string, as: string|null}|null $from */
        $from = $components['from'] ?? null;
        if ($from === null) {
            return '';
        }

        $table = $from['as'] !== null ? $from['table'] . ' as ' . $from['as'] : $from['table'];

        return 'from ' . $this->wrapTable($table);
    }

    /** @param array<string, mixed> $components */
    protected function compileJoins(array $components): string
    {
        /** @var list<array<string, mixed>> $joins */
        $joins = $components['joins'] ?? [];
        if ($joins === []) {
            return '';
        }

        $parts = [];
        foreach ($joins as $join) {
            $table = $join['as'] !== null ? $join['table'] . ' as ' . $join['as'] : $join['table'];
            $sql = \strtolower($join['type']) . ' join ' . $this->wrapTable($table);

            if (isset($join['raw'])) {
                $sql .= ' ' . $join['raw'];
            } elseif (($join['wheres'] ?? []) !== []) {
                /** @var list<array<string, mixed>> $joinWheres */
                $joinWheres = $join['wheres'];
                $sql .= ' on ' . $this->compileWhereList($joinWheres);
            }

            $parts[] = $sql;
        }

        return \implode(' ', $parts);
    }

    /** @param array<string, mixed> $components */
    protected function compileWhereSection(array $components): string
    {
        /** @var list<array<string, mixed>> $wheres */
        $wheres = $components['wheres'] ?? [];
        if ($wheres === []) {
            return '';
        }

        return 'where ' . $this->compileWhereList($wheres);
    }

    /**
     * @param list<array<string, mixed>> $wheres
     */
    protected function compileWhereList(array $wheres): string
    {
        $parts = [];
        foreach ($wheres as $i => $where) {
            $compiled = $this->compileWhere($where);
            $parts[] = $i === 0 ? $compiled : $where['boolean'] . ' ' . $compiled;
        }

        return \implode(' ', $parts);
    }

    /** @param array<string, mixed> $where */
    protected function compileWhere(array $where): string
    {
        $not = ($where['not'] ?? false) ? 'not ' : '';

        return match ($where['type']) {
            'Basic' => $this->wrap($where['column']) . ' ' . $this->validateOperator((string) $where['operator']) . ' ?',
            'Column' => $this->wrap($where['first']) . ' ' . $this->validateOperator((string) $where['operator']) . ' ' . $this->wrap($where['second']),
            'Raw' => $this->safeRaw((string) $where['sql']),
            'Nested' => '(' . $this->compileWhereList($where['wheres']) . ')',
            'In' => $this->wrap($where['column']) . ' ' . $not . 'in (' . $where['placeholder'] . ')',
            'Null' => $this->wrap($where['column']) . ' is ' . $not . 'null',
            'Between' => $this->wrap($where['column']) . ' ' . $not . 'between ? and ?',
            'Exists' => $not . 'exists (' . $where['sql'] . ')',
            default => throw new \InvalidArgumentException(\sprintf('Unknown where type [%s].', (string) $where['type'])),
        };
    }

    /** @param array<string, mixed> $components */
    protected function compileGroups(array $components): string
    {
        /** @var list<string|Expression> $groups */
        $groups = $components['groups'] ?? [];
        if ($groups === []) {
            return '';
        }

        return 'group by ' . $this->columnize($groups);
    }

    /** @param array<string, mixed> $components */
    protected function compileHavings(array $components): string
    {
        /** @var list<array<string, mixed>> $havings */
        $havings = $components['havings'] ?? [];
        if ($havings === []) {
            return '';
        }

        $parts = [];
        foreach ($havings as $i => $having) {
            $compiled = match ($having['type']) {
                'Raw' => $this->safeRaw((string) $having['sql']),
                default => $this->wrap($having['column']) . ' ' . $this->validateOperator((string) $having['operator']) . ' ?',
            };
            $parts[] = $i === 0 ? $compiled : $having['boolean'] . ' ' . $compiled;
        }

        return 'having ' . \implode(' ', $parts);
    }

    /** @param array<string, mixed> $components */
    protected function compileUnions(array $components): string
    {
        /** @var list<array{sql: string, all: bool}> $unions */
        $unions = $components['unions'] ?? [];
        if ($unions === []) {
            return '';
        }

        $sql = '';
        foreach ($unions as $union) {
            $sql .= 'union ' . ($union['all'] ? 'all ' : '') . $union['sql'] . ' ';
        }

        return \trim($sql);
    }

    /** @param array<string, mixed> $components */
    protected function compileOrders(array $components): string
    {
        /** @var list<array{column: string|Expression, direction: string}|Expression> $orders */
        $orders = $components['orders'] ?? [];
        if ($orders === []) {
            return '';
        }

        $parts = [];
        foreach ($orders as $order) {
            if ($order instanceof Expression) {
                $parts[] = $this->safeRaw($order->value);
                continue;
            }
            $parts[] = $this->wrap($order['column']) . ' ' . $this->validateDirection((string) $order['direction']);
        }

        return 'order by ' . \implode(', ', $parts);
    }

    /** @param array<string, mixed> $components */
    protected function compileLimitOffset(array $components): string
    {
        $limit = $components['limit'] ?? null;
        $offset = $components['offset'] ?? null;

        $sql = '';
        if ($limit !== null) {
            $sql .= 'limit ' . \max(0, (int) $limit);
        }
        if ($offset !== null) {
            $sql .= ($sql !== '' ? ' ' : '') . 'offset ' . \max(0, (int) $offset);
        }

        return $sql;
    }

    /** @param array<string, mixed> $components */
    protected function compileLock(array $components): string
    {
        $lock = $components['lock'] ?? null;

        return match ($lock) {
            'forUpdate' => 'for update',
            'sharedLock' => $this->sharedLockClause(),
            default => '',
        };
    }

    protected function sharedLockClause(): string
    {
        return 'lock in share mode';
    }

    // ─── INSERT / UPDATE / DELETE ─────────────────────────────────────────

    /**
     * @param array<string, mixed>       $values
     * @param list<array<string, mixed>> $rows
     */
    public function compileInsert(string $table, array $values = [], array $rows = [], string $mode = ''): string
    {
        $allRows = $rows !== [] ? $rows : ($values !== [] ? [$values] : []);
        if ($allRows === []) {
            return 'insert into ' . $this->wrapTable($table) . ' default values';
        }

        $columns = \array_keys($allRows[0]);
        $rowPlaceholder = '(' . $this->parameterize(\array_fill_keys($columns, null)) . ')';
        $placeholders = \array_fill(0, \count($allRows), $rowPlaceholder);

        $prefix = match ($mode) {
            'ignore-mysql' => 'insert ignore into ',
            'ignore-sqlite' => 'insert or ignore into ',
            default => 'insert into ',
        };

        $sql = $prefix . $this->wrapTable($table)
            . ' (' . $this->columnize($columns) . ') values ' . \implode(', ', $placeholders);

        if ($mode === 'ignore-pgsql') {
            $sql .= ' on conflict do nothing';
        }

        return $sql;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $uniqueBy
     * @param list<string> $update
     */
    public function compileUpsert(string $table, array $columns, array $uniqueBy, array $update, int $rowCount = 1): string
    {
        $rowPlaceholder = '(' . $this->parameterize(\array_fill_keys($columns, null)) . ')';

        $sql = 'insert into ' . $this->wrapTable($table)
            . ' (' . $this->columnize($columns) . ') values '
            . \implode(', ', \array_fill(0, \max(1, $rowCount), $rowPlaceholder));

        return $sql . ' ' . $this->upsertConflictClause($uniqueBy) . ' do update set '
            . \implode(', ', $this->upsertSets($columns, $uniqueBy, $update));
    }

    /**
     * @param list<string> $columns
     * @param list<string> $uniqueBy
     * @param list<string> $update
     *
     * @return list<string>
     */
    protected function upsertSets(array $columns, array $uniqueBy, array $update): array
    {
        $updateColumns = $update === [] ? \array_values(\array_diff($columns, $uniqueBy)) : $update;

        $sets = [];
        foreach ($updateColumns as $column) {
            $wrapped = $this->wrapValue($column);
            $sets[] = $wrapped . ' = ' . $this->upsertValueReference($wrapped);
        }

        return $sets;
    }

    /** ارجاع به مقدار جدید در upsert (mysql: values(col) / pgsql+sqlite: excluded.col). */
    abstract protected function upsertValueReference(string $wrappedColumn): string;

    /** @param list<string> $uniqueBy */
    abstract protected function upsertConflictClause(array $uniqueBy): string;

    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $values
     */
    public function compileUpdate(array $components, array $values): string
    {
        $sets = [];
        foreach ($values as $column => $value) {
            if ($value instanceof Expression) {
                $sets[] = $this->wrap((string) $column) . ' = ' . $this->safeRaw($value->value);
                continue;
            }
            if (\str_contains((string) $column, '->')) {
                $sets[] = $this->compileJsonUpdate((string) $column);
                continue;
            }
            $sets[] = $this->wrap((string) $column) . ' = ?';
        }

        $from = $components['from'];
        $table = $from['as'] !== null ? $from['table'] . ' as ' . $from['as'] : $from['table'];
        $sql = 'update ' . $this->wrapTable($table) . ' set ' . \implode(', ', $sets);

        if (($components['wheres'] ?? []) !== []) {
            $sql .= ' ' . $this->compileWhereSection($components);
        }
        if (($components['orders'] ?? []) !== []) {
            $sql .= ' ' . $this->compileOrders($components);
        }
        if (isset($components['limit']) && $this->supportsUpdateLimit()) {
            $sql .= ' limit ' . (int) $components['limit'];
        }

        return $sql;
    }

    protected function supportsUpdateLimit(): bool
    {
        return false;
    }

    protected function compileJsonUpdate(string $column): string
    {
        [$col, $path] = \explode('->', $column, 2);
        $jsonPath = '$."' . \str_replace(['->', '.'], ['"."', '"."'], $path) . '"';

        return $this->wrap($col) . ' = json_set(' . $this->wrap($col) . ', \'' . $jsonPath . '\', ?)';
    }

    /** @param array<string, mixed> $components */
    public function compileDelete(array $components): string
    {
        $from = $components['from'];
        $table = $from['as'] !== null ? $from['table'] . ' as ' . $from['as'] : $from['table'];
        $sql = 'delete from ' . $this->wrapTable($table);

        if (($components['joins'] ?? []) !== []) {
            $sql .= ' using ' . $this->wrapTable($table) . ' ' . $this->compileJoins($components);
        }
        if (($components['wheres'] ?? []) !== []) {
            $sql .= ' ' . $this->compileWhereSection($components);
        }
        if (isset($components['limit']) && $this->supportsUpdateLimit()) {
            $sql .= ' limit ' . (int) $components['limit'];
        }

        return $sql;
    }

    public function compileTruncate(string $table): string
    {
        return 'truncate ' . $this->wrapTable($table);
    }

    /** @param array<int|string, mixed> $values */
    public function parameterize(array $values): string
    {
        return \implode(', ', \array_map(fn ($v): string => $v instanceof Expression ? $this->safeRaw($v->value) : '?', $values));
    }

    // ─── اعتبارسنجی operator / direction / raw ────────────────────────────

    protected function validateOperator(string $operator): string
    {
        $normalized = \strtolower(\trim($operator));
        if (!\in_array($normalized, self::OPERATORS, true)) {
            throw new UnsafeQueryException(
                context: ['operator' => $operator],
                hint: 'فقط operatorهای allowlist مجازند.',
            );
        }

        return $normalized;
    }

    protected function validateDirection(string $direction): string
    {
        $normalized = \strtolower(\trim($direction));
        if (!\in_array($normalized, self::DIRECTIONS, true)) {
            throw new UnsafeQueryException(
                context: ['direction' => $direction],
                hint: 'جهت مرتب‌سازی فقط asc یا desc.',
            );
        }

        return $normalized;
    }

    /** 🔒 raw نباید ابرمتغیرهای درخواست را مستقیم درون خود داشته باشد (Second-Order guard). */
    protected function safeRaw(string $sql): string
    {
        $this->assertSafeRaw($sql, 'raw');

        return $sql;
    }

    protected function assertSafeRaw(string $sql, string $context): void
    {
        if (\preg_match('/[\$](?:_GET|_POST|_REQUEST|_COOKIE)/', $sql) === 1) {
            throw new UnsafeQueryException(context: ['context' => $context]);
        }
    }
}
