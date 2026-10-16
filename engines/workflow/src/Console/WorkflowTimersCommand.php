<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Workflow\WorkflowManager;

/**
 * تَوان | Tavaan — Workflow
 * WorkflowTimersCommand — پردازش تایمرهای سررسیده (DOC-071 §6).
 *
 * زمان‌بندی: `Schedule::command('workflow:timers')->everyMinute()`.
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class WorkflowTimersCommand extends Command
{
    protected string $signature = 'workflow:timers {--dry : فقط شمارش، بدون اجرا}';

    protected string $description = 'پردازش تایمرهای گردش‌کارهای سررسیده';

    public function handle(): int
    {
        /** @var WorkflowManager $workflows */
        $workflows = $this->app->make('workflow');
        $processor = $workflows->timerProcessor();

        if ($this->option('dry') !== null) {
            $this->comment('حالت dry — اجرای واقعی انجام نمی‌شود.');

            return self::SUCCESS;
        }

        $fired = $processor->process();
        $this->info(\sprintf('✓ %d تایمر اجرا شد.', $fired));

        return self::SUCCESS;
    }
}
