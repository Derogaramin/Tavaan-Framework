<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Migration\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Migration
 * MigrationLockedException — یک migration دیگر در حال اجراست (DOC-049 §4).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrationLockedException extends TavaanException
{
    public function __construct(?string $hint = null)
    {
        parent::__construct(
            'migration.locked',
            [],
            $hint ?? 'یک migration دیگر در حال اجراست. کمی صبر کنید.',
        );
    }
}
