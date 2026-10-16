<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\MorphMap;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — ORM
 * MorphToMany — چند-به-چند چندریختی (pivot با ستون‌های type+id).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class MorphToMany extends Relation
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
        private readonly string $morphType,     // taggable_type
        private readonly string $morphId,       // taggable_id
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

    public function addConstraints(): void
    {
        $this->applyJoin();
        $this->query
            ->where($this->pivotTable . '.' . $this->morphType, MorphMap::alias($this->parent::class))
            ->where($this->pivotTable . '.' . $this->morphId, $this->parent->getAttribute($this->parentKey));
    }

    public function addEagerConstraints(array $models): void
    {
        $this->applyJoin();
        $keys = $this->parentKeys($models, $this->parentKey);
        $this->query
            ->where($this->pivotTable . '.' . $this->morphType, MorphMap::alias($this->parent::class))
            ->whereIn($this->pivotTable . '.' . $this->morphId, $keys);
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

        $selects = [$relatedTable . '.*', $this->pivotTable . '.' . $this->morphId . ' as pivot_morph_id'];
        foreach ($this->pivotColumns as $column) {
            $selects[] = $this->pivotTable . '.' . $column . ' as pivot_' . $column;
        }
        $this->query->select(...$selects);
    }

    /** @return list<Model> */
    public function getResults(): mixed
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

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Model>
     */
    private function hydrate(array $rows): array
    {
        $related = $this->newRelated();
        $models = [];
        foreach ($rows as $row) {
            $pivot = ['morph_id' => $row['pivot_morph_id'] ?? null];
            unset($row['pivot_morph_id']);
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
        // dictionary دستی از pivot.morph_id
        $dictionary = [];
        foreach ($results as $result) {
            $pivot = $result->getAttribute('pivot');
            $key = (string) (\is_array($pivot) ? ($pivot['morph_id'] ?? '') : '');
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

    /** @param array<string, mixed> $attributes */
    public function attach(int|string|Model $related, array $attributes = []): void
    {
        $id = $related instanceof Model ? $related->getAttribute($this->relatedKey) : $related;

        $record = [
            $this->morphType => MorphMap::alias($this->parent::class),
            $this->morphId => $this->parent->getAttribute($this->parentKey),
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
            ->where($this->morphType, MorphMap::alias($this->parent::class))
            ->where($this->morphId, $this->parent->getAttribute($this->parentKey));

        if ($relatedId !== null) {
            $query->where($this->relatedPivotKey, $relatedId);
        }

        return $query->delete();
    }
}
