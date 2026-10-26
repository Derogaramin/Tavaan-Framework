<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * CheckTimersCommand — `bpms:check-timers` (تایمر/escalation — §9).
 */

namespace Modules\Bpms\Console\Commands;

use Modules\Bpms\Application\UseCases\TimerService;
use Tavaan\Engine\Console\Command;

/**
 * بررسی تایمرهای فرایند (DOC-098 §9 — AC-BPMS-13):
 * کارهای بازِ سررسیدگذشته → اجرای اکشن تایمر (escalate داخلی یا اکشن
 * ثبت‌شده) + رویداد `bpms.task.escalated` + اعلان. idempotent.
 *
 * روی schedule هر دقیقه ثبت می‌شود (Provider — الگوی crm:check-sla).
 * `tvn bpms:check-timers`
 *
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class CheckTimersCommand extends Command
{
    protected string $signature = 'bpms:check-timers';

    protected string $description = 'بررسی تایمرهای BPMS — escalate/اکشن خودکار برای کارهای سررسیدگذشته';

    public function handle(): int
    {
        /** @var TimerService $timers */
        $timers = $this->app->make(TimerService::class);

        $processed = $timers->processDue();

        if ($processed > 0) {
            $this->warn(\sprintf('⚠ %d کار سررسیدگذشته پردازش شد (escalate/auto-action).', $processed));
        } else {
            $this->info('تایمرها سالم — کار سررسیدگذشته‌ای یافت نشد.');
        }

        return self::SUCCESS;
    }
}
