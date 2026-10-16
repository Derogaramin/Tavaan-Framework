<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Api
 * ApiException — خطای عمومی موتور API (DOC-044).
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
class ApiException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public static function for(string $messageId, array $context = [], ?string $hint = null): static
    {
        return new static($messageId, $context, $hint);
    }

    public static function unsupportedVersion(string $version): static
    {
        return new static('api.unsupported_version', ['version' => $version]);
    }
}
