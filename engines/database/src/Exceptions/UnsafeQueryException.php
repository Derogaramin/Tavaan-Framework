<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Database
 * UnsafeQueryException — raw SQL با ورودی مستقیم کاربر (DOC-047 §10).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class UnsafeQueryException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null)
    {
        parent::__construct(
            'database.unsafe_raw',
            $context,
            $hint ?? 'در whereRaw/orderByRaw مقدار کاربر را فقط با binding منتقل کنید.',
        );
    }
}
