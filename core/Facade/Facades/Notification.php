<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade آگاه‌سازی چندکاناله (Notification).
 *
 * @method static array send(\Tavaan\Core\Contracts\Notification\Notifiable|iterable $notifiables, \Tavaan\Engine\Notification\Notification $notification)
 * @method static list<string> sendNow(\Tavaan\Core\Contracts\Notification\Notifiable $notifiable, \Tavaan\Engine\Notification\Notification $notification)
 * @method static \Tavaan\Engine\Notification\AnonymousNotifiable route(string $channel, mixed $route)
 * @method static \Tavaan\Engine\Notification\Channels\ChannelInterface channel(string $name)
 * @method static void extend(string $name, \Closure $factory)
 * @method static void useSmsGateway(\Tavaan\Engine\Notification\Sms\SmsGatewayInterface $gateway)
 * @method static \Tavaan\Engine\Notification\Sms\SmsGatewayInterface smsGateway()
 * @method static \Tavaan\Engine\Notification\Channels\DatabaseChannel database()
 * @method static \Tavaan\Engine\Notification\NotificationSender fake()
 * @method static bool isFaking()
 * @method static void assertSentTo(\Tavaan\Core\Contracts\Notification\Notifiable $notifiable, string $notificationClass, ?callable $callback = null)
 * @method static void assertNotSentTo(\Tavaan\Core\Contracts\Notification\Notifiable $notifiable, string $notificationClass)
 * @method static void assertSentCount(int $count)
 * @method static list<array{notifiable: \Tavaan\Core\Contracts\Notification\Notifiable, notification: \Tavaan\Engine\Notification\Notification, channels: list<string>}> sent()
 * @mixin \Tavaan\Engine\Notification\NotificationSender
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class Notification extends Facade
{
    protected static function accessor(): string
    {
        return 'notification';
    }
}
