<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Channels;

use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\Exceptions\NotificationException;
use Tavaan\Engine\Notification\Notification;

/**
 * تَوان | Tavaan — Notification
 * BroadcastChannel — انتشار رویداد برای موتور realtime (M6؛ DOC-066 §2).
 *
 * پیام `toBroadcast()` به‌عنوان رویداد dispatch می‌شود؛ مصرف‌کنندهٔ
 * نهایی (SSE/WS) در فاز M6 با موتور realtime تکمیل می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class BroadcastChannel implements ChannelInterface
{
    public function __construct(private readonly ?DispatcherInterface $events = null)
    {
    }

    public function send(Notifiable $notifiable, mixed $message, Notification $notification): mixed
    {
        if ($this->events === null) {
            throw NotificationException::channelUnavailable('broadcast');
        }

        // شیء رویداد باید publish شود؛ آرایه → wrapper استاندارد
        $event = \is_object($message)
            ? $message
            : new NotificationBroadcast($notification, $notifiable, $message);

        $this->events->dispatch($event);

        return $event;
    }

    public function name(): string
    {
        return 'broadcast';
    }
}

/**
 * رویداد پیش‌فرض کانال broadcast.
 *
 * @api 1.0
 */
final class NotificationBroadcast
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly Notification $notification,
        public readonly Notifiable $notifiable,
        public readonly array $data,
    ) {
    }
}
