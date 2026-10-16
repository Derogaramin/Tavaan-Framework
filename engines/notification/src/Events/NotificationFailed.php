<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Events;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\Notification;

/**
 * تَوان | Tavaan — Notification
 * NotificationFailed — شکست یک کانال (کانال‌های دیگر ادامه می‌یابند).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class NotificationFailed
{
    public function __construct(
        public readonly Notifiable $notifiable,
        public readonly Notification $notification,
        public readonly string $channel,
        public readonly string $error,
    ) {
    }
}
