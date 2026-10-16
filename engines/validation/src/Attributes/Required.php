<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Attributes;

/**
 * تَوان | Tavaan — Validation
 * Required — فیلد الزامی (Attribute-based — DOC-051 §6).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class Required extends ValidationAttribute
{
    public function passes(string $attribute, mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    public function implicit(): bool
    {
        return true;
    }

    public function message(): string
    {
        return 'validation.required';
    }
}
