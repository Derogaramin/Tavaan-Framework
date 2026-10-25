<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketWasAssigned — تیکت به کارشناس تخصیص یافت (DOC-096 §3.3: assignment).
 */

namespace Modules\Crm\Domain\Events;

/**
 * افزودنی مستند نسبت به فهرست §5 — تخصیص پس از ساخت رخ می‌دهد و
 * notification کارشناس به رویداد اختصاصی خودش نیاز دارد.
 *
 * @api 1.0
 */
final readonly class TicketWasAssigned
{
    public function __construct(
        public string $ticketId,
        public string $ticketNumber,
        public string $subject,
        public string $priority,
        public int $assignedTo,
        public ?string $slaDueAt,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.ticket.assigned';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'subject' => $this->subject,
            'priority' => $this->priority,
            'assigned_to' => $this->assignedTo,
            'sla_due_at' => $this->slaDueAt,
        ];
    }
}
