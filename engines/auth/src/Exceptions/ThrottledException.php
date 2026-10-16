<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Exceptions;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * تَوان | Tavaan — Auth
 * ThrottledException — نرخ تلاش بیش از حد (۴۲۹ با Retry-After).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class ThrottledException extends HttpException
{
    public function __construct(public readonly int $retryAfterSeconds = 60, ?string $hint = null)
    {
        parent::__construct(
            429,
            'auth.throttled',
            ['retry_after' => $retryAfterSeconds],
            $hint ?? 'تعداد تلاش‌ها بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.',
            null,
            ['Retry-After' => (string) $retryAfterSeconds],
        );
    }
}
