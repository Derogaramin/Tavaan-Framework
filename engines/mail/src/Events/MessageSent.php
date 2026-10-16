<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Events;

use Tavaan\Engine\Mail\Message;
use Tavaan\Engine\Mail\SentMessage;

/**
 * تَوان | Tavaan — Mail
 * MessageSent — پس از ارسال موفق به transport (DOC-065).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class MessageSent
{
    public function __construct(
        public readonly Message $message,
        public readonly SentMessage $sent,
    ) {
    }
}
