<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade رویدادها.
 *
 * @method static void listen(string|array $events, callable|string|array $listener, int $priority = 0)
 * @method static mixed dispatch(object|string $event, mixed $payload = [], bool $halt = false)
 * @method static bool hasListeners(string $event)
 * @mixin \Tavaan\Engine\Event\EventDispatcher
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final class Event extends Facade
{
    protected static function accessor(): string
    {
        return 'events';
    }
}
