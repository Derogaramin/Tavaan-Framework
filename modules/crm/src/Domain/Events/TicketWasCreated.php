<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketWasCreated — تیکت پشتیبانی ساخته شد (DOC-096 §5: SLA timer, assignment).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class TicketWasCreated
{
    public function __construct(
        public string $ticketId,
        public string $ticketNumber,
        public string $subject,
        public string $priority,
        public ?string $contactId,
        public ?int $assignedTo,
        public ?string $slaDueAt,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.ticket.created';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'subject' => $this->subject,
            'priority' => $this->priority,
            'contact_id' => $this->contactId,
            'assigned_to' => $this->assignedTo,
            'sla_due_at' => $this->slaDueAt,
        ];
    }
}
