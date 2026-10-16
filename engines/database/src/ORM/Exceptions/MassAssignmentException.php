<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — ORM
 * MassAssignmentException — تلاش برای پرکردن فیلد guarded (DOC-050 §9).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class MassAssignmentException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null)
    {
        parent::__construct('orm.mass_assignment', $context, $hint);
    }
}
