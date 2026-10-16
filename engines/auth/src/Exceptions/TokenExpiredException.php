<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Exceptions;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * تَوان | Tavaan — Auth
 * TokenExpiredException — توکن منقضی شده (۴۰۱).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class TokenExpiredException extends HttpException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null)
    {
        parent::__construct(401, 'auth.token.expired', $context, $hint);
    }
}
