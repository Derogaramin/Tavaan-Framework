<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Rules;

use Tavaan\Core\Contracts\Validation\RuleInterface;

/**
 * تَوان | Tavaan — Validation
 * InRule — قاعدهٔ in/not_in به‌صورت Rule object (DOC-051 §2.1).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class InRule implements RuleInterface
{
    /** @var list<string> */
    private readonly array $values;

    /** @param list<string|int|float> $values */
    public function __construct(array $values, private readonly bool $not = false)
    {
        $this->values = \array_map(static fn ($v): string => (string) $v, \array_values($values));
    }

    public function passes(string $attribute, mixed $value): bool
    {
        $in = \in_array((string) $value, $this->values, true);

        return $this->not ? !$in : $in;
    }

    public function message(): string
    {
        return $this->not ? 'validation.not_in' : 'validation.in';
    }

    public function implicit(): bool
    {
        return false;
    }
}
