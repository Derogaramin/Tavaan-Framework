<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Attributes;

/**
 * تَوان | Tavaan — Validation
 * Email — قالب ایمیل معتبر (Attribute-based — DOC-051 §6).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class Email extends ValidationAttribute
{
    public function passes(string $attribute, mixed $value): bool
    {
        return \is_string($value) && \filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function message(): string
    {
        return 'validation.email';
    }
}
