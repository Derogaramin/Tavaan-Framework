<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * CheckSlaCommand — `crm:check-sla` (پایش SLA + escalation خودکار).
 */

namespace Modules\Crm\Console\Commands;

use Modules\Crm\Application\UseCases\SlaMonitorService;
use Tavaan\Engine\Console\Command;

/**
 * پایش SLA تیکت‌ها (DOC-096 §3.3 — AC-CRM-08):
 *
 * - openهای سررسیدگذشته → escalate + رویداد `crm.ticket.sla_breached` + اعلان
 * - resolvedهای قدیمی → close خودکار (config: tickets.auto_close_days)
 *
 * روی schedule هر دقیقه ثبت می‌شود (Provider — الگوی news).
 * `tvn crm:check-sla`
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class CheckSlaCommand extends Command
{
    protected string $signature = 'crm:check-sla';

    protected string $description = 'پایش SLA تیکت‌ها — escalation خودکار + بستن resolvedهای قدیمی';

    public function handle(): int
    {
        /** @var SlaMonitorService $monitor */
        $monitor = $this->app->make(SlaMonitorService::class);

        $escalated = $monitor->escalateOverdue();
        $closed = $monitor->autoCloseResolved();

        if ($escalated['escalated'] > 0) {
            $this->warn(\sprintf('⚠ %d تیکت escalate شد: %s', $escalated['escalated'], \implode('، ', $escalated['ticket_numbers'])));
        }
        if ($closed['closed'] > 0) {
            $this->info(\sprintf('%d تیکت resolved به‌طور خودکار بسته شد.', $closed['closed']));
        }
        if ($escalated['escalated'] === 0 && $closed['closed'] === 0) {
            $this->info('SLA سالم — موردی یافت نشد.');
        }

        return self::SUCCESS;
    }
}
