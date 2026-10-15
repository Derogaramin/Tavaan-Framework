<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * TEV002 — عبور از حد مجاز Listenerها (DoS protection).
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final class MaxListenersExceededException extends TavaanException
{
    public static function for(string $event, int $max): self
    {
        return new self(
            messageId: 'event.max_listeners',
            context: ['event' => $event, 'max' => $max],
            hint: 'event.max_listeners را بازبینی کنید یا Listenerها را ادغام نمایید.',
        );
    }
}
