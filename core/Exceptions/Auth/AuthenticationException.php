<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Auth;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * خطای احراز هویت (401) — حوزهٔ کد: TAU.
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
class AuthenticationException extends TavaanException
{
    /** @param list<string> $guards */
    public function __construct(
        string $messageId = 'auth.credentials.invalid',
        array $context = [],
        ?string $hint = null,
        public readonly array $guards = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageId, $context, $hint, null, 0, $previous);
    }
}
