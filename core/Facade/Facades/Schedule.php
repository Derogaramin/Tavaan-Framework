<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade زمان‌بندی (Schedule).
 *
 * @method static \Tavaan\Engine\Schedule\ScheduleEvent command(string $command, array $arguments = [])
 * @method static \Tavaan\Engine\Schedule\ScheduleEvent job(object $job)
 * @method static \Tavaan\Engine\Schedule\ScheduleEvent call(callable $callback)
 * @method static \Tavaan\Engine\Schedule\ScheduleEvent exec(string $command)
 * @method static \Tavaan\Engine\Schedule\ScheduleEvent add(\Tavaan\Engine\Schedule\ScheduleEvent $event)
 * @method static list<\Tavaan\Engine\Schedule\ScheduleEvent> events()
 * @method static list<array{event: \Tavaan\Engine\Schedule\ScheduleEvent, status: string, exit: int, output: string, ms: int}> run(?\DateTimeImmutable $now = null)
 * @method static string defaultTimezone()
 * @mixin \Tavaan\Engine\Schedule\ScheduleManager
 * @api 1.0
 * @see docs/03-engines/schedule.md
 */
final class Schedule extends Facade
{
    protected static function accessor(): string
    {
        return 'schedule';
    }
}
