<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

/**
 * تَوان | Tavaan — ORM
 * MorphOne — رابطهٔ چندریختی یک-به-یک.
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class MorphOne extends MorphOneOrMany
{
    public function getResults(): mixed
    {
        if ($this->parent->getAttribute($this->localKey) === null) {
            return null;
        }

        $row = $this->query->first();

        return $row === null ? null : $this->newRelated()->newFromRow($row);
    }

    public function match(array $models, array $results): array
    {
        $dictionary = $this->buildDictionary($results, $this->morphId);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if ($key !== null && isset($dictionary[(string) $key])) {
                $model->setRelation($this->relationName(), $dictionary[(string) $key][0]);
            }
        }

        return $models;
    }
}
