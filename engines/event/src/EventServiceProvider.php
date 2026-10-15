<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Event
 * EventServiceProvider — ثبت EventDispatcher به‌عنوان `events`.
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventDispatcher::class, static function (ContainerInterface $c): EventDispatcher {
            $config = $c->bound('config') ? (array) $c->make('config')->get('event', []) : [];

            return new EventDispatcher($c, $config);
        });
        $this->app->alias(EventDispatcher::class, 'events');
        $this->app->singleton(DispatcherInterface::class, static fn (ContainerInterface $c): DispatcherInterface => $c->make('events'));
    }

    /** @return list<string> */
    public function provides(): array
    {
        return ['events', DispatcherInterface::class, EventDispatcher::class];
    }
}
