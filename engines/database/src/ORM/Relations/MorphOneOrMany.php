<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\MorphMap;

/**
 * تَوان | Tavaan — ORM
 * MorphOneOrMany — پایهٔ روابط چندریختی (ستون‌های {name}_type و {name}_id).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
abstract class MorphOneOrMany extends Relation
{
    /**
     * @param class-string<Model> $relatedClass
     */
    public function __construct(
        Model $parent,
        string $relatedClass,
        protected readonly string $morphType,   // مثلاً commentable_type
        protected readonly string $morphId,     // مثلاً commentable_id
        protected readonly string $localKey,
    ) {
        parent::__construct($parent);
        $this->bootQuery($relatedClass);
    }

    protected function morphTypeValue(): string
    {
        return MorphMap::alias($this->parent::class);
    }

    public function addConstraints(): void
    {
        $this->query
            ->where($this->morphType, $this->morphTypeValue())
            ->where($this->morphId, $this->parent->getAttribute($this->localKey));
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->parentKeys($models, $this->localKey);
        $this->query
            ->where($this->morphType, $this->morphTypeValue())
            ->whereIn($this->morphId, $keys);
    }

    /** ساخت مدل مرتبط با پرکردن خودکار type/id. */
    public function make(array $attributes): Model
    {
        $related = $this->newRelated();
        $attributes[$this->morphType] = $this->morphTypeValue();
        $attributes[$this->morphId] = $this->parent->getAttribute($this->localKey);
        $related->fill($attributes);

        return $related;
    }

    public function create(array $attributes): Model
    {
        $model = $this->make($attributes);
        $model->save();

        return $model;
    }

    public function morphIdColumn(): string
    {
        return $this->morphId;
    }
}
