<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Http;

/**
 * عدم تطبیق توکن CSRF (وضعیت 419).
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
final class CsrfTokenMismatchException extends HttpException
{
    public function __construct(?string $hint = null, ?\Throwable $previous = null)
    {
        parent::__construct(419, 'http.csrf_mismatch', [], $hint ?? 'توکن CSRF منقضی یا نامعتبر است؛ صفحه را تازه کنید.', null, [], 0, $previous);
    }
}
