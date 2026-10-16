<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Database
 * ConnectionException — شکست برقراری اتصال به دیتابیس.
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class ConnectionException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null, ?\Throwable $previous = null)
    {
        parent::__construct('database.connection_failed', $context, $hint ?? 'اعتبارنامه و دسترسی DB را بررسی کنید.', null, 0, $previous);
    }
}
