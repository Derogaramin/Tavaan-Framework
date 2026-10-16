<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Attributes;

/**
 * تَوان | Tavaan — Validation
 * MaxLength — سقف طول رشته (Attribute-based — DOC-051 §6).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class MaxLength extends ValidationAttribute
{
    public function __construct(public readonly int $max)
    {
    }

    public function passes(string $attribute, mixed $value): bool
    {
        return \mb_strlen((string) $value, 'UTF-8') <= $this->max;
    }

    public function message(): string
    {
        return 'validation.max.string';
    }
}
