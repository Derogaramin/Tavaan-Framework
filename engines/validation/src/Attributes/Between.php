<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Attributes;

/**
 * تَوان | Tavaan — Validation
 * Between — بازهٔ عددی (Attribute-based — DOC-051 §6).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class Between extends ValidationAttribute
{
    public function __construct(public readonly float $min, public readonly float $max)
    {
    }

    public function passes(string $attribute, mixed $value): bool
    {
        return \is_numeric($value) && $value + 0 >= $this->min && $value + 0 <= $this->max;
    }

    public function message(): string
    {
        return 'validation.between.numeric';
    }
}
