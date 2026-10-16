<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\MorphMap;

/**
 * تَوان | Tavaan — ORM
 * MorphTo — سمت معکوس رابطهٔ چندریختی (مدل والد از type resolve می‌شود).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class MorphTo extends Relation
{
    /** @var array<string, Model> کش والدین resolve شده */
    private array $resolved = [];

    public function __construct(
        Model $parent,
        private readonly string $morphType,
        private readonly string $morphId,
    ) {
        parent::__construct($parent);

        // related class از دادهٔ والد (ستون type) تعیین می‌شود
        $this->query = $parent->newModelQuery()->getQuery();
        $this->addConstraints();
    }

    public function addConstraints(): void
    {
        $type = (string) ($this->parent->getAttribute($this->morphType) ?? '');
        $class = MorphMap::classFor($type);

        if ($class === null) {
            return;
        }

        /** @var Model $related */
        $related = new $class();
        $this->query = $related->newModelQuery()->getQuery();
        $this->query->where($related->getKeyName(), $this->parent->getAttribute($this->morphId));
    }

    public function addEagerConstraints(array $models): void
    {
        // گروه‌بندی والدین بر اساس type و اجرای یک کوئری per-type
        $byType = [];
        foreach ($models as $model) {
            $type = (string) ($model->getAttribute($this->morphType) ?? '');
            $id = $model->getAttribute($this->morphId);
            if ($type !== '' && $id !== null) {
                $byType[$type][] = $id;
            }
        }

        foreach ($byType as $type => $ids) {
            $class = MorphMap::classFor((string) $type);
            if ($class === null) {
                continue;
            }
            /** @var Model $related */
            $related = new $class();
            foreach ($related->newModelQuery()->getQuery()->whereIn($related->getKeyName(), \array_values(\array_unique($ids)))->get() as $row) {
                $model = $related->newFromRow($row);
                $this->resolved[(string) $type . ':' . (string) $model->getKey()] = $model;
            }
        }
    }

    public function getResults(): mixed
    {
        $type = (string) ($this->parent->getAttribute($this->morphType) ?? '');
        $id = $this->parent->getAttribute($this->morphId);

        if ($type === '' || $id === null) {
            return null;
        }

        $row = $this->query->first();
        if ($row === null) {
            return null;
        }

        $class = MorphMap::classFor($type);
        if ($class === null) {
            return null;
        }

        /** @var Model $related */
        $related = new $class();

        return $related->newFromRow($row);
    }

    public function getEager(): array
    {
        return \array_values($this->resolved);
    }

    public function match(array $models, array $results): array
    {
        foreach ($models as $model) {
            $type = (string) ($model->getAttribute($this->morphType) ?? '');
            $id = $model->getAttribute($this->morphId);
            $key = $type . ':' . (string) $id;

            if (isset($this->resolved[$key])) {
                $model->setRelation($this->relationName(), $this->resolved[$key]);
            }
        }

        return $models;
    }

    /** والد را به مدل جدید وصل می‌کند. */
    public function associate(Model $related): Model
    {
        $this->parent->setAttribute($this->morphType, MorphMap::alias($related::class));
        $this->parent->setAttribute($this->morphId, $related->getKey());
        $this->parent->setRelation($this->relationName(), $related);

        return $this->parent;
    }
}
