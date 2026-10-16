<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;

/**
 * تَوان | Tavaan — ORM
 * HasMany — رابطهٔ یک-به-چند (کلید خارجی در جدول مرتبط).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class HasMany extends HasOneOrMany
{
    /** @return list<Model> */
    public function getResults(): array
    {
        if ($this->parent->getAttribute($this->localKey) === null) {
            return [];
        }

        $related = $this->newRelated();
        $models = [];
        foreach ($this->query->get() as $row) {
            $models[] = $related->newFromRow($row);
        }

        return $models;
    }

    public function match(array $models, array $results): array
    {
        $dictionary = $this->buildDictionary($results, $this->foreignKey);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($this->relationName(), $key !== null ? ($dictionary[(string) $key] ?? []) : []);
        }

        return $models;
    }
}
