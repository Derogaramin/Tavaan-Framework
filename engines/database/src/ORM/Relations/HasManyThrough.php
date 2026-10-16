<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;

/**
 * تَوان | Tavaan — ORM
 * HasOneThrough / HasManyThrough — رابطهٔ «از میان» یک مدل واسط (DOC-050 §4).
 *
 * مثال: Country → (users) → Posts
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
class HasManyThrough extends Relation
{
    /**
     * @param class-string<Model> $relatedClass
     * @param class-string<Model> $throughClass
     */
    public function __construct(
        Model $parent,
        string $relatedClass,
        private readonly string $throughClass,
        private readonly string $firstKey,     // کلید والد در جدول واسط
        private readonly string $secondKey,    // کلید واسط در جدول مقصد
        private readonly string $localKey,     // کلید والد
    ) {
        parent::__construct($parent);
        $this->bootQuery($relatedClass);
    }

    public function addConstraints(): void
    {
        $through = $this->newThrough();
        $this->query->join(
            $through->getTable(),
            $through->getTable() . '.' . $through->getKeyName(),
            '=',
            $this->newRelated()->getTable() . '.' . $this->secondKey,
        );
        $this->query->where($through->getTable() . '.' . $this->firstKey, $this->parent->getAttribute($this->localKey));
    }

    public function addEagerConstraints(array $models): void
    {
        $through = $this->newThrough();
        $this->query->join(
            $through->getTable(),
            $through->getTable() . '.' . $through->getKeyName(),
            '=',
            $this->newRelated()->getTable() . '.' . $this->secondKey,
        );
        $keys = $this->parentKeys($models, $this->localKey);
        $this->query->whereIn($through->getTable() . '.' . $this->firstKey, $keys);
        $this->query->select($this->newRelated()->getTable() . '.*', $through->getTable() . '.' . $this->firstKey . ' as through_first_key');
    }

    /** @return list<Model> */
    public function getResults(): mixed
    {
        $related = $this->newRelated();
        $models = [];
        foreach ($this->query->get() as $row) {
            unset($row['through_first_key']);
            $models[] = $related->newFromRow($row);
        }

        return $models;
    }

    public function getEager(): array
    {
        $related = $this->newRelated();
        $models = [];
        foreach ($this->query->get() as $row) {
            $model = $related->newFromRow($row);
            $model->setAttribute('through_first_key', $row['through_first_key'] ?? null);
            $models[] = $model;
        }

        return $models;
    }

    public function match(array $models, array $results): array
    {
        $dictionary = $this->buildDictionary($results, 'through_first_key');

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $group = $key !== null ? ($dictionary[(string) $key] ?? []) : [];
            foreach ($group as $item) {
                $item->forgetAttribute('through_first_key');
            }
            $model->setRelation($this->relationName(), $this instanceof HasOneThrough ? ($group[0] ?? null) : $group);
        }

        return $models;
    }

    private function newThrough(): Model
    {
        $class = $this->throughClass;

        return new $class();
    }
}
