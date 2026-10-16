<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Jobs;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Notification\Notification;
use Tavaan\Engine\Notification\NotificationSender;

/**
 * تَوان | Tavaan — Notification
 * SendQueuedNotification — job ارسال آگاه‌سازی صف‌روند (DOC-066 §3).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class SendQueuedNotification implements ShouldQueue
{
    public ?int $tries = 3;

    public ?int $timeout = 60;

    public function __construct(
        public readonly Notifiable $notifiable,
        public readonly Notification $notification,
    ) {
    }

    public function handle(NotificationSender $sender): void
    {
        $sender->sendNow($this->notifiable, $this->notification);
    }
}
