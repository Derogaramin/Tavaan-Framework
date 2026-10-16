<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schedule\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Schedule\ScheduleManager;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Schedule
 * ScheduleListCommand — فهرست رویدادهای زمان‌بندی + اجرای بعدی (preview).
 *
 * @api 1.0
 * @see docs/03-engines/schedule.md
 */
final class ScheduleListCommand extends Command
{
    protected string $signature = 'schedule:list {--next=1 : تعداد اجرای بعدی برای نمایش}';

    protected string $description = 'فهرست وظایف زمان‌بندی‌شده با اجرای بعدی';

    public function handle(): int
    {
        /** @var ScheduleManager $manager */
        $manager = $this->app->make(ScheduleManager::class);

        $events = $manager->events();
        if ($events === []) {
            $this->comment('هیچ رویداد زمان‌بندی‌شده‌ای ثبت نشده است (routes/console.php).');

            return self::SUCCESS;
        }

        $next = \max(1, (int) ($this->option('next') ?? 1));
        $now = Clock::now();
        $rows = [];

        foreach ($events as $event) {
            $local = $event->timezoneName() !== null
                ? $now->setTimezone(new \DateTimeZone($event->timezoneName()))
                : $now->setTimezone(new \DateTimeZone($manager->defaultTimezone()));

            $runs = $event->cronExpression()->nextRunDates($next, $local);
            $nextRuns = \array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d H:i'), $runs);

            $flags = [];
            if ($event->wantsWithoutOverlapping()) {
                $flags[] = 'no-overlap';
            }
            if ($event->wantsOnOneServer()) {
                $flags[] = 'one-server';
            }
            if ($event->wantsBackground()) {
                $flags[] = 'background';
            }

            $rows[] = [
                $event->expression(),
                $event->type(),
                $event->description(),
                \implode('، ', $nextRuns),
                \implode(',', $flags),
                (string) ($event->timezoneName() ?? $manager->defaultTimezone()),
            ];
        }

        $this->table(['Cron', 'نوع', 'وظیفه', 'اجرای بعدی', 'پرچم‌ها', 'Timezone'], $rows);

        return self::SUCCESS;
    }
}
