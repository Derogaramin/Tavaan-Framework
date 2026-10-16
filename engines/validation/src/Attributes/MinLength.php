<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Attributes;

/**
 * تَوان | Tavaan — Validation
 * MinLength — کف طول رشته (Attribute-based — DOC-051 §6).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class MinLength extends ValidationAttribute
{
    public function __construct(public readonly int $min)
    {
    }

    public function passes(string $attribute, mixed $value): bool
    {
        return \mb_strlen((string) $value, 'UTF-8') >= $this->min;
    }

    public function message(): string
    {
        return 'validation.min.string';
    }
}
