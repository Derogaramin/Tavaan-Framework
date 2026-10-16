<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Rules;

use Tavaan\Core\Contracts\Validation\RuleInterface;

/**
 * تَوان | Tavaan — Validation
 * Rule — کارخانهٔ Rule objectها (DOC-051 §2.1: `Rule::in(['a','b'])`).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class Rule
{
    private function __construct()
    {
    }

    /** @param list<string|int|float> $values */
    public static function in(array $values): RuleInterface
    {
        return new InRule($values, not: false);
    }

    /** @param list<string|int|float> $values */
    public static function notIn(array $values): RuleInterface
    {
        return new InRule($values, not: true);
    }

    public static function unique(string $table, ?string $column = null, int|string|null $ignoreId = null, string $ignoreColumn = 'id'): RuleInterface
    {
        return new UniqueRule($table, $column, $ignoreId, $ignoreColumn);
    }

    public static function exists(string $table, ?string $column = null): RuleInterface
    {
        return new ExistsRule($table, $column);
    }
}
