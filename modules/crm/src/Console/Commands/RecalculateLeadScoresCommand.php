<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * RecalculateLeadScoresCommand — `crm:recalculate-scores`.
 */

namespace Modules\Crm\Console\Commands;

use Modules\Crm\Application\UseCases\LeadService;
use Tavaan\Engine\Console\Command;

/**
 * بازشماری امتیاز سرنخ‌های باز (نگهداری — پس از import یا تغییر وزن‌ها).
 *
 * `tvn crm:recalculate-scores`
 *
 * @api 1.0
 */
final class RecalculateLeadScoresCommand extends Command
{
    protected string $signature = 'crm:recalculate-scores';

    protected string $description = 'بازشماری امتیاز سرنخ‌های باز (Lead Scoring §3.2)';

    public function handle(): int
    {
        /** @var LeadService $leads */
        $leads = $this->app->make(LeadService::class);

        $count = $leads->recalculateAll();

        $this->info(\sprintf('امتیاز %d سرنخ به‌روزرسانی شد.', $count));

        return self::SUCCESS;
    }
}
