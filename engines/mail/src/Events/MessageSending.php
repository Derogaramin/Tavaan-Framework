<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Events;

use Tavaan\Engine\Mail\Message;

/**
 * تَوان | Tavaan — Mail
 * MessageSending — پیش از ارسال به transport (DOC-065).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class MessageSending
{
    public function __construct(public readonly Message $message)
    {
    }
}
