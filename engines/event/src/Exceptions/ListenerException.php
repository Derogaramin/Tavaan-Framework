<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * TEV001 — خطا در اجرای Listener.
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final class ListenerException extends TavaanException
{
    public static function for(string $event, string $listener, \Throwable $previous): self
    {
        return new self(
            messageId: 'event.listener_failed',
            context: ['event' => $event, 'listener' => $listener],
            hint: 'خطای درون Listener را رفع کنید؛ با stop_on_error=false اجرا ادامه می‌یابد.',
            previous: $previous,
        );
    }
}
