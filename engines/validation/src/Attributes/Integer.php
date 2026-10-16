<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Attributes;

/**
 * تَوان | Tavaan — Validation
 * Integer — مقدار صحیح (Attribute-based — DOC-051 §6).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class Integer extends ValidationAttribute
{
    public function passes(string $attribute, mixed $value): bool
    {
        return \is_int($value) || (\is_string($value) && \preg_match('/^-?\d+$/', $value) === 1);
    }

    public function message(): string
    {
        return 'validation.integer';
    }
}
