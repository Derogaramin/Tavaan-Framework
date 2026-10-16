<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — ORM
 * BelongsToMany — رابطهٔ چند-به-چند با جدول pivot (DOC-050 §4).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class BelongsToMany extends Relation
{
    private bool $withPivotTimestamps = false;

    /**
     * @param class-string<Model> $relatedClass
     * @param list<string>        $pivotColumns
     */
    public function __construct(
        Model $parent,
        string $relatedClass,
        private readonly string $pivotTable,
        private readonly string $foreignPivotKey,
        private readonly string $relatedPivotKey,
        private readonly string $parentKey,
        private readonly string $relatedKey,
        array $pivotColumns = [],
    ) {
        $this->pivotColumns = $pivotColumns;
        parent::__construct($parent);
        $this->bootQuery($relatedClass);
    }

    public function withTimestamps(): self
    {
        $this->withPivotTimestamps = true;
        $this->pivotColumns = \array_values(\array_unique([...$this->pivotColumns, 'created_at', 'updated_at']));

        return $this;
    }

    /** @param list<string> $columns */
    public function withPivot(array $columns): self
    {
        $this->pivotColumns = \array_values(\array_unique([...$this->pivotColumns, ...$columns]));

        return $this;
    }

    public function addConstraints(): void
    {
        $this->applyJoin();
        $this->query->where($this->pivotTable . '.' . $this->foreignPivotKey, $this->parent->getAttribute($this->parentKey));
    }

    public function addEagerConstraints(array $models): void
    {
        $this->applyJoin();
        $keys = $this->parentKeys($models, $this->parentKey);
        $this->query->whereIn($this->pivotTable . '.' . $this->foreignPivotKey, $keys);
    }

    private function applyJoin(): void
    {
        $relatedTable = $this->newRelated()->getTable();
        $this->query->join(
            $this->pivotTable,
            $this->pivotTable . '.' . $this->relatedPivotKey,
            '=',
            $relatedTable . '.' . $this->relatedKey,
        );

        // انتخاب ستون‌های pivot (با prefix برای تشخیص)
        $selects = [$relatedTable . '.*'];
        foreach ($this->pivotColumns as $column) {
            $selects[] = $this->pivotTable . '.' . $column . ' as pivot_' . $column;
        }
        $this->query->select(...$selects);
    }

    public function getResults(): array
    {
        if ($this->parent->getAttribute($this->parentKey) === null) {
            return [];
        }

        return $this->hydrate($this->query->get());
    }

    public function getEager(): array
    {
        return $this->hydrate($this->query->get());
    }

    /** @param list<array<string, mixed>> $rows @return list<Model> */
    private function hydrate(array $rows): array
    {
        $related = $this->newRelated();
        $models = [];
        foreach ($rows as $row) {
            $pivot = [];
            foreach ($this->pivotColumns as $column) {
                if (\array_key_exists('pivot_' . $column, $row)) {
                    $pivot[$column] = $row['pivot_' . $column];
                    unset($row['pivot_' . $column]);
                }
            }
            $model = $related->newFromRow($row);
            $model->setAttribute('pivot', $pivot);
            $models[] = $model;
        }

        return $models;
    }

    public function match(array $models, array $results): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $pivot = $result->getAttribute('pivot');
            $key = (string) (\is_array($pivot) ? ($pivot[$this->foreignPivotKey] ?? '') : '');
            if ($key !== '') {
                $dictionary[$key][] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            $model->setRelation($this->relationName(), $key !== null ? ($dictionary[(string) $key] ?? []) : []);
        }

        return $models;
    }

    // ─── pivot management ─────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function attach(int|string|Model $related, array $attributes = []): void
    {
        $id = $related instanceof Model ? $related->getAttribute($this->relatedKey) : $related;

        $record = [
            $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
            $this->relatedPivotKey => $id,
            ...$attributes,
        ];
        if ($this->withPivotTimestamps) {
            $now = Clock::utcNow()->format('Y-m-d H:i:s.u');
            $record['created_at'] ??= $now;
            $record['updated_at'] ??= $now;
        }

        $this->parent->connection()->table($this->pivotTable)->insert($record);
    }

    public function detach(int|string|null $relatedId = null): int
    {
        $query = $this->parent->connection()->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey));

        if ($relatedId !== null) {
            $query->where($this->relatedPivotKey, $relatedId);
        }

        return $query->delete();
    }

    /**
     * همگام‌سازی: افزودن/حذف تفاوت‌ها.
     *
     * @param list<int|string>|array<int|string, array<string, mixed>> $ids
     */
    public function sync(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $key => $value) {
            if (\is_int($key)) {
                $normalized[(string) $value] = [];
            } else {
                $normalized[(string) $key] = $value;
            }
        }

        $connection = $this->parent->connection();
        /** @var list<string> $current */
        $current = $connection->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->pluck($this->relatedPivotKey);
        $current = \array_map('strval', $current);

        $toDetach = \array_values(\array_diff($current, \array_keys($normalized)));
        $toAttach = [];
        foreach ($normalized as $id => $attributes) {
            if (!\in_array((string) $id, $current, true)) {
                $toAttach[$id] = $attributes;
            }
        }

        foreach ($toDetach as $id) {
            $this->detach($id);
        }
        foreach ($toAttach as $id => $attributes) {
            $this->attach($id, $attributes);
        }

        return ['attached' => \array_keys($toAttach), 'detached' => $toDetach];
    }

    /**
     * زیرکوئری شمارش correlated برای withCount.
     *
     * @return array{sql: string, bindings: list<mixed>}
     */
    public function buildCountSubQuery(string $correlatedParentKey): array
    {
        $relatedTable = $this->newRelated()->getTable();

        return [
            'sql' => 'select count(*) from ' . $relatedTable
                . ' inner join ' . $this->pivotTable
                . ' on ' . $this->pivotTable . '.' . $this->relatedPivotKey . ' = ' . $relatedTable . '.' . $this->relatedKey
                . ' where ' . $this->pivotTable . '.' . $this->foreignPivotKey . ' = ' . $correlatedParentKey,
            'bindings' => [],
        ];
    }

    public function toggle(int|string $id): void
    {
        $connection = $this->parent->connection();
        $exists = $connection->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->where($this->relatedPivotKey, $id)
            ->exists();

        $exists ? $this->detach($id) : $this->attach($id);
    }
}
