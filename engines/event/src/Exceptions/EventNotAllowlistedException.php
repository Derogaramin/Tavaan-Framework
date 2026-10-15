<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * TEV004 — dispatch رویداد رشته‌ای خارج از allowlist (امنیت).
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final class EventNotAllowlistedException extends TavaanException
{
    public static function for(string $event): self
    {
        return new self(
            messageId: 'event.not_allowlisted',
            context: ['event' => $event],
            hint: '🔒 نام رویدادهای رشته‌ای را با Event::allowDispatch() مجاز کنید.',
        );
    }
}
