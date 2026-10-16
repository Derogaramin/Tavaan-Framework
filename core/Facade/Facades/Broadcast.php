<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade پخش بلادرنگ (Broadcast — DOC-045).
 *
 * @method static void channel(string $pattern, \Closure $callback)
 * @method static int broadcast(object $event)
 * @method static void publish(string $channel, string $name, array $payload)
 * @method static bool|array authorize(object $user, string $channel)
 * @method static string sign(string $channel, object $user, ?array $presence = null)
 * @method static array|null verify(string $token)
 * @method static \Tavaan\Core\Contracts\Realtime\BrokerInterface broker(?string $name = null)
 * @method static \Tavaan\Engine\Realtime\ChannelRegistry channels()
 * @method static bool isPrivate(string $channel)
 * @method static bool isPresence(string $channel)
 * @method static void extend(string $name, \Closure $factory)
 * @mixin \Tavaan\Engine\Realtime\RealtimeManager
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class Broadcast extends Facade
{
    protected static function accessor(): string
    {
        return 'broadcast';
    }
}
