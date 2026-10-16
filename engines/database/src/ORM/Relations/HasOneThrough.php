<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

/**
 * تَوان | Tavaan — ORM
 * HasOneThrough — یک رکورد از میان مدل واسط.
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class HasOneThrough extends HasManyThrough
{
    public function getResults(): mixed
    {
        $results = parent::getResults();

        return \is_array($results) ? ($results[0] ?? null) : null;
    }
}
