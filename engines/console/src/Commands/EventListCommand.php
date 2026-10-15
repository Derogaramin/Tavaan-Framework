<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Event\EventDispatcher;

/**
 * فهرست رویدادها و Listenerها با اولویت (AC-EVT-12).
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final class EventListCommand extends Command
{
    protected string $signature = 'event:list';

    protected string $description = 'فهرست رویدادهای ثبت‌شده و Listenerهای هرکدام';

    public function handle(): int
    {
        if (!$this->app->bound('events')) {
            $this->warn('  موتور event فعال نیست.');

            return self::SUCCESS;
        }

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->app->make('events');

        $reflection = new \ReflectionProperty(EventDispatcher::class, 'registry');
        $registry = $reflection->getValue($dispatcher);
        $all = $registry->all();

        if ($all === []) {
            $this->comment('  هیچ رویدادی ثبت نشده است.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($all as $event => $listeners) {
            foreach ($listeners as $listener) {
                $rows[] = [$event, $listener->describe(), (string) $listener->priority];
            }
        }

        $this->table(['Event', 'Listener', 'Priority'], $rows);

        return self::SUCCESS;
    }
}
