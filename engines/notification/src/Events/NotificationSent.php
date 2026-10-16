<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Events;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\Notification;

/**
 * تَوان | Tavaan — Notification
 * NotificationSent — پس از ارسال موفق یک کانال.
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class NotificationSent
{
    public function __construct(
        public readonly Notifiable $notifiable,
        public readonly Notification $notification,
        public readonly string $channel,
        public readonly mixed $result = null,
    ) {
    }
}
