<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Channels;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\Exceptions\NotificationException;
use Tavaan\Engine\Notification\Messages\SmsMessage;
use Tavaan\Engine\Notification\Notification;
use Tavaan\Engine\Notification\Sms\SmsGatewayInterface;
use Tavaan\Engine\Notification\Sms\SmsResult;

/**
 * تَوان | Tavaan — Notification
 * SmsChannel — کانال پیامک با درگاه‌های ایرانی (DOC-066 §2/§5).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class SmsChannel implements ChannelInterface
{
    public function __construct(private readonly ?SmsGatewayInterface $gateway = null)
    {
    }

    public function send(Notifiable $notifiable, mixed $message, Notification $notification): mixed
    {
        if ($this->gateway === null) {
            throw NotificationException::channelUnavailable('sms');
        }

        if (!$message instanceof SmsMessage) {
            throw NotificationException::invalidMessage('sms', SmsMessage::class);
        }

        // گیرنده: از route نوتیفای‌شونده (اگر پیام خودش recipient ندارد)
        $sms = $message;
        if ($sms->recipients() === []) {
            $route = $notifiable->routeNotificationFor('sms', $notification);
            if (\is_string($route) && $route !== '') {
                $sms = (clone $sms)->to($route);
            } elseif (\is_array($route) && $route !== []) {
                $sms = (clone $sms)->to(\array_values(\array_map(strval(...), $route)));
            } else {
                throw NotificationException::missingRoute('sms', $notifiable);
            }
        }

        $result = $this->gateway->send($sms);

        if (!$result instanceof SmsResult || !$result->successful) {
            throw NotificationException::channelUnavailable('sms: ' . ($result->error ?? 'send failed'));
        }

        return $result;
    }

    public function name(): string
    {
        return 'sms';
    }
}
