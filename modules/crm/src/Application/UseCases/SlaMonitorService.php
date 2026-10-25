<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * SlaMonitorService — پایش SLA و escalation خودکار (DOC-096 §3.3 — AC-CRM-08).
 */

namespace Modules\Crm\Application\UseCases;

use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Support\Time\Clock;

/**
 * SlaMonitorService — اسکن تیکت‌های openِ سررسیدگذشته:
 *
 * - `escalateOverdue()`: breach → escalated_at + رویداد `crm.ticket.sla_breached`
 *   (مصرف‌کننده: notification به نقش‌های escalation + گزارش) — idempotent
 * - `autoCloseResolved()`: resolvedهای قدیمی → closed (config: tickets.auto_close_days)
 *
 * ⏱ زمان از Clock — در تست با Fake قطعی است (DOC-110).
 * تولید: `tvn crm:check-sla` (روی schedule هر دقیقه — الگوی news).
 *
 * @api 1.0
 */
final class SlaMonitorService
{
    /**
     * @param array<string, mixed> $config کلید config('crm')
     */
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly array $config = [],
    ) {
    }

    /**
     * @return array{escalated: int, ticket_numbers: list<string>}
     */
    public function escalateOverdue(): array
    {
        $now = Clock::utcNow();
        $escalated = [];

        foreach ($this->tickets->openOverdue($now) as $ticket) {
            if ($ticket->escalateForSla($now)) {
                $this->tickets->save($ticket);

                foreach ($ticket->pullEvents() as $event) {
                    if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                        Event::dispatch($event->eventName(), $event->toPayload());
                    }
                }

                $escalated[] = $ticket->ticketNumber();
            }
        }

        return ['escalated' => \count($escalated), 'ticket_numbers' => $escalated];
    }

    /**
     * @return array{closed: int}
     */
    public function autoCloseResolved(): array
    {
        $days = (int) ($this->config['tickets']['auto_close_days'] ?? 0);
        if ($days <= 0) {
            return ['closed' => 0];
        }

        $cutoff = Clock::utcNow()->sub(new \DateInterval('P' . $days . 'D'));
        $closed = 0;

        foreach ($this->tickets->resolvedOlderThan($cutoff) as $ticket) {
            $ticket->close();
            $this->tickets->save($ticket);
            $closed++;
        }

        return ['closed' => $closed];
    }

    /** اجرای کامل پایش (cron). @return array<string, int> */
    public function run(): array
    {
        return [
            ...$this->escalateOverdue(),
            ...$this->autoCloseResolved(),
        ];
    }
}
