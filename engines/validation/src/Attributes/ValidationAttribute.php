<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Attributes;

use Tavaan\Core\Contracts\Validation\RuleInterface;

/**
 * تَوان | Tavaan — Validation
 * ValidationAttribute — پایهٔ Attributeهای اعتبارسنجی (DOC-051 §6).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
abstract class ValidationAttribute implements RuleInterface
{
    public function implicit(): bool
    {
        return false;
    }

    public function message(): string
    {
        $short = \substr(static::class, (int) \strrpos(static::class, '\\') + 1);

        return 'validation.' . \strtolower(\preg_replace('/(?<!^)[A-Z]/', '_$0', $short) ?? $short);
    }
}
