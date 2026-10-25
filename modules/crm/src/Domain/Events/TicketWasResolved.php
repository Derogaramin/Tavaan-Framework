<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketWasResolved — تیکت حل شد (DOC-096 §5: satisfaction survey).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class TicketWasResolved
{
    public function __construct(
        public string $ticketId,
        public string $ticketNumber,
        public ?string $contactId,
        public ?int $assignedTo,
        public ?int $resolutionMinutes,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.ticket.resolved';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'contact_id' => $this->contactId,
            'assigned_to' => $this->assignedTo,
            'resolution_minutes' => $this->resolutionMinutes,
        ];
    }
}
