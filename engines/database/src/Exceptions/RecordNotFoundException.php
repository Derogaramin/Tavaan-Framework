<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Database
 * RecordNotFoundException — رکورد درخواستی یافت نشد (نسخهٔ DB از 404).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class RecordNotFoundException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null)
    {
        parent::__construct('database.record_not_found', $context, $hint);
    }
}
