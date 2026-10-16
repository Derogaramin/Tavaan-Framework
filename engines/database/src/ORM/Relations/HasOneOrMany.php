<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;

/**
 * تَوان | Tavaan — ORM
 * HasOneOrMany — پایهٔ HasOne/HasMany (کلید خارجی در جدول مرتبط).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
abstract class HasOneOrMany extends Relation
{
    /**
     * @param class-string<Model> $relatedClass
     */
    public function __construct(
        Model $parent,
        string $relatedClass,
        protected readonly string $foreignKey,
        protected readonly string $localKey,
    ) {
        parent::__construct($parent);
        $this->bootQuery($relatedClass);
    }

    public function addConstraints(): void
    {
        $value = $this->parent->getAttribute($this->localKey);
        $this->query->where($this->foreignKey, $value);
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->parentKeys($models, $this->localKey);
        $this->query->whereIn($this->foreignKey, $keys);
    }

    public function foreignKey(): string
    {
        return $this->foreignKey;
    }

    /** ساخت مدل مرتبط با پرکردن خودکار کلید خارجی. */
    public function make(array $attributes): Model
    {
        $related = $this->newRelated();
        $attributes[$this->foreignKey] = $this->parent->getAttribute($this->localKey);
        $related->fill($attributes);

        return $related;
    }

    /** ساخت + ذخیرهٔ مدل مرتبط. */
    public function create(array $attributes): Model
    {
        $model = $this->make($attributes);
        $model->save();

        return $model;
    }
}
