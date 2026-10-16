<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Exceptions;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * تَوان | Tavaan — Auth
 * TokenInvalidException — توکن نامعتبر (۴۰۱).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class TokenInvalidException extends HttpException
{
    /** @param array<string, mixed> $context */
    public function __construct(string $messageId = 'auth.token.invalid', array $context = [], ?string $hint = null, ?\Throwable $previous = null)
    {
        parent::__construct(401, $messageId, $context, $hint, null, [], 0, $previous);
    }
}
