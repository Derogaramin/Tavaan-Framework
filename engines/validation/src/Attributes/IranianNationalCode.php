<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Attributes;

use Tavaan\Engine\Validation\Support\IranianChecks;

/**
 * تَوان | Tavaan — Validation
 * IranianNationalCode — کد ملی ایران (Attribute-based — DOC-051 §6).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class IranianNationalCode extends ValidationAttribute
{
    public function passes(string $attribute, mixed $value): bool
    {
        return IranianChecks::nationalCode((string) $value);
    }

    public function message(): string
    {
        return 'validation.iran_national_code';
    }
}
