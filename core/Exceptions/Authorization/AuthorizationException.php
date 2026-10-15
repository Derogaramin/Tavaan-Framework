<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Authorization;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * خطای دسترسی (403) — حوزهٔ کد: TAZ.
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
class AuthorizationException extends TavaanException
{
    public function __construct(
        string $messageId = 'authorization.denied',
        array $context = [],
        ?string $hint = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageId, $context, $hint, null, 0, $previous);
    }
}
