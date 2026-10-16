<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Exceptions;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * تَوان | Tavaan — Auth
 * AccountBannedException — حساب کاربری مسدود است (۴۰۳).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class AccountBannedException extends HttpException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null)
    {
        parent::__construct(403, 'auth.account_banned', $context, $hint);
    }
}
