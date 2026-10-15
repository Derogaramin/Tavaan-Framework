<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container\Exceptions;

use Tavaan\Core\Exceptions\Core\ContainerException;

/**
 * TCR004 — پارامتر Constructor بدون type (در حالت strict ممنوع).
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final class UntypedParameterException extends ContainerException
{
    public static function for(string $concrete, string $parameter): self
    {
        return new self(
            messageId: 'container.untyped_parameter',
            context: ['class' => $concrete, 'parameter' => $parameter],
            hint: 'به پارامتر type صریح بدهید (DOC-010 T-01/T-02) یا container.strict.allow_untyped_parameters را فعال کنید.',
        );
    }
}
