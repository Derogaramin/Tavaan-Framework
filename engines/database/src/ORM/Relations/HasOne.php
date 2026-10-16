<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;

/**
 * تَوان | Tavaan — ORM
 * HasOne — رابطهٔ یک-به-یک (کلید خارجی در جدول مرتبط).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class HasOne extends HasOneOrMany
{
    public function getResults(): ?Model
    {
        if ($this->parent->getAttribute($this->localKey) === null) {
            return null;
        }

        $row = $this->query->first();

        return $row === null ? null : $this->newRelated()->newFromRow($row);
    }

    public function match(array $models, array $results): array
    {
        $dictionary = $this->buildDictionary($results, $this->foreignKey);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if ($key !== null && isset($dictionary[(string) $key])) {
                $model->setRelation($this->relationName(), $dictionary[(string) $key][0]);
            }
        }

        return $models;
    }
}
