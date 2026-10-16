<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Database
 * MixedBindingException — ترکیب binding نامی و موقعیتی مجاز نیست (DOC-047 §6.1).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class MixedBindingException extends TavaanException
{
    public function __construct(?string $hint = null)
    {
        parent::__construct(
            'database.mixed_bindings',
            [],
            $hint ?? 'binding نامی (:name) و موقعیتی (?) را در یک کوئری ترکیب نکنید.',
        );
    }
}
