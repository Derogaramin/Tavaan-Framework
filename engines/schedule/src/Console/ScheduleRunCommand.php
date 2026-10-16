<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schedule\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Schedule\ScheduleManager;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Schedule
 * ScheduleRunCommand — چرخهٔ اجرای زمان‌بندی (DOC-062 §4).
 *
 * تنها entry لازم در crontab:
 * ```
 * * * * * * php /path/bin/tvn schedule:run >> /dev/null 2>&1
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/schedule.md
 */
final class ScheduleRunCommand extends Command
{
    protected string $signature = 'schedule:run {--verbose-output : نمایش خروجی وظایف}';

    protected string $description = 'اجرای وظایف زمان‌بندی‌شدهٔ سررسیده (هر دقیقه از cron)';

    public function handle(): int
    {
        /** @var ScheduleManager $manager */
        $manager = $this->app->make(ScheduleManager::class);

        $now = Clock::now();
        $results = $manager->run($now);

        if ($results === []) {
            $this->comment(\sprintf('(%s) هیچ وظیفهٔ سررسیده‌ای نیست.', $now->format('Y-m-d H:i')));

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($results as $result) {
            $event = $result['event'];
            $icon = match ($result['status']) {
                'finished' => '✅',
                'queued' => '📮',
                'background' => '🌙',
                'failed' => '🔴',
                default => '⏭️',
            };

            $this->line(\sprintf(
                '%s %s — %s (%dms)',
                $icon,
                $event->summary(),
                $result['status'],
                $result['ms'],
            ));

            if ($result['status'] === 'failed') {
                $failed++;
            }

            if ($this->option('verbose-output') && $result['output'] !== '') {
                $this->comment(\trim((string) $result['output']));
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
