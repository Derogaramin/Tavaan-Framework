<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Tenancy
 * TenancyException — پایهٔ خطاهای چنداجاره‌ای (DOC-082E).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
class TenancyException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public static function for(string $messageId, array $context = [], ?string $hint = null): static
    {
        return new static($messageId, $context, $hint);
    }
}
