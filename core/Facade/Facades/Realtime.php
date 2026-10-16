<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade بلادرنگ (Realtime — DOC-045 §7).
 *
 * @method static array presence(string $channel)
 * @method static void join(string $channel, array $member)
 * @method static void leave(string $channel, string $memberId)
 * @method static int broadcast(object $event)
 * @method static void publish(string $channel, string $name, array $payload)
 * @mixin \Tavaan\Engine\Realtime\RealtimeFacadeRoot
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class Realtime extends Facade
{
    protected static function accessor(): string
    {
        return 'realtime.facade';
    }
}
