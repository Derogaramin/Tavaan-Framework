<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketSlaBreached — نقض SLA (DOC-096 §5: escalation workflow — AC-CRM-08).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class TicketSlaBreached
{
    public function __construct(
        public string $ticketId,
        public string $ticketNumber,
        public string $priority,
        public string $slaDueAt,
        public ?int $assignedTo,
        public int $overdueMinutes,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.ticket.sla_breached';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'priority' => $this->priority,
            'sla_due_at' => $this->slaDueAt,
            'assigned_to' => $this->assignedTo,
            'overdue_minutes' => $this->overdueMinutes,
        ];
    }
}
