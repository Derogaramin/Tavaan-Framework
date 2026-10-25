<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * SendCampaignsCommand — `crm:send-campaigns` (کمپین‌های زمان‌بندی‌شده).
 */

namespace Modules\Crm\Console\Commands;

use Modules\Crm\Application\UseCases\CampaignService;
use Tavaan\Engine\Console\Command;

/**
 * ارسال کمپین‌های scheduledِ سررسیدرسیده — idempotent (uk recipient).
 * روی schedule هر دقیقه (Provider).
 *
 * `tvn crm:send-campaigns`
 *
 * @api 1.0
 */
final class SendCampaignsCommand extends Command
{
    protected string $signature = 'crm:send-campaigns';

    protected string $description = 'ارسال کمپین‌های زمان‌بندی‌شدهٔ CRM';

    public function handle(): int
    {
        /** @var CampaignService $campaigns */
        $campaigns = $this->app->make(CampaignService::class);

        $sent = $campaigns->sendDue();

        $this->info($sent > 0
            ? \sprintf('%d کمپین ارسال شد.', $sent)
            : 'کمپین زمان‌بندی‌شدهٔ سررسیدرسیده‌ای نبود.');

        return self::SUCCESS;
    }
}
