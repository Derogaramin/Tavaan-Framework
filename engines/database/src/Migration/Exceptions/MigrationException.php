<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Migration\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Migration
 * MigrationException — خطای عمومی اجرا/یافتن migration.
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrationException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(string $messageId, array $context = [], ?string $hint = null, ?\Throwable $previous = null)
    {
        parent::__construct($messageId, $context, $hint, null, 0, $previous);
    }
}
