<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Notification
 * NotificationException — خطاهای موتور آگاه‌سازی (DOC-066).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
class NotificationException extends TavaanException
{
    public static function channelUnavailable(string $channel): static
    {
        return new static('notification.channel_unavailable', ['channel' => $channel]);
    }

    public static function unknownChannel(string $channel): static
    {
        return new static('notification.unknown_channel', ['channel' => $channel]);
    }

    public static function invalidMessage(string $channel, string $expected): static
    {
        return new static('notification.invalid_message', ['channel' => $channel, 'expected' => $expected]);
    }

    public static function missingRoute(string $channel, object $notifiable): static
    {
        return new static('notification.missing_route', [
            'channel' => $channel,
            'notifiable' => $notifiable::class,
        ], 'routeNotificationFor(\'' . $channel . '\') یا property مربوطه را تعریف کنید.');
    }

    public static function rateLimited(string $channel, string $key): static
    {
        return new static('notification.rate_limited', ['channel' => $channel, 'key' => $key]);
    }

    public static function assertionFailed(string $expected): static
    {
        return new static('notification.assertion_failed', ['expected' => $expected]);
    }
}
