<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;

/**
 * تَوان | Tavaan — ORM
 * BelongsTo — رابطهٔ معکوس (کلید خارجی در جدول والد).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class BelongsTo extends Relation
{
    /**
     * @param class-string<Model> $relatedClass
     */
    public function __construct(
        Model $parent,
        string $relatedClass,
        private readonly string $foreignKey,
        private readonly string $ownerKey,
    ) {
        parent::__construct($parent);
        $this->bootQuery($relatedClass);
    }

    public function addConstraints(): void
    {
        $value = $this->parent->getAttribute($this->foreignKey);
        $this->query->where($this->ownerKey, $value);
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->parentKeys($models, $this->foreignKey);
        $this->query->whereIn($this->ownerKey, $keys);
    }

    public function getResults(): ?Model
    {
        if ($this->parent->getAttribute($this->foreignKey) === null) {
            return null;
        }

        $row = $this->query->first();

        return $row === null ? null : $this->newRelated()->newFromRow($row);
    }

    public function match(array $models, array $results): array
    {
        $dictionary = $this->buildDictionary($results, $this->ownerKey);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            if ($key !== null && isset($dictionary[(string) $key])) {
                $model->setRelation($this->relationName(), $dictionary[(string) $key][0]);
            }
        }

        return $models;
    }

    public function foreignKey(): string
    {
        return $this->foreignKey;
    }

    public function ownerKey(): string
    {
        return $this->ownerKey;
    }

    /** والد را به مدل مرتبط جدید وصل می‌کند. */
    public function associate(Model $related): Model
    {
        $this->parent->setAttribute($this->foreignKey, $related->getAttribute($this->ownerKey));
        $this->parent->setRelation($this->relationName(), $related);

        return $this->parent;
    }

    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);

        return $this->parent;
    }
}
