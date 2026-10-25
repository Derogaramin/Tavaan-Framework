<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ApiCrmController — API ورود سرنخ/تیکت (DOC-096 §7: فرم وب‌سایت → lead).
 */

namespace Modules\Crm\Http\Controllers\Api;

use Modules\Crm\Application\UseCases\ContactService;
use Modules\Crm\Application\UseCases\LeadService;
use Modules\Crm\Application\UseCases\TicketService;
use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * ApiCrmController — JSON برای یکپارچگی‌های خارجی:
 *
 * - `POST /api/v1/crm/leads`   — فرم وب‌سایت → contact + lead (با score)
 * - `POST /api/v1/crm/tickets` — فرم/ایمیل → تیکت (number + SLA)
 * - `GET  /api/v1/crm/tickets/{number}` — وضعیت تیکت
 *
 * 🔒 Throttle روی هر سه (routes/api.php) + ValidationException → 422.
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class ApiCrmController
{
    public function __construct(
        private readonly LeadService $leads,
        private readonly ContactService $contacts,
        private readonly TicketService $tickets,
        private readonly TicketRepositoryInterface $ticketRepo,
    ) {
    }

    /**
     * ورود سرنخ — {name, email, title, value?, mobile?, source?}.
     *
     * @return array<string, mixed>
     */
    public function createLead(RequestInterface $request): array
    {
        $validated = Validator::validate($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'integer', 'min:0'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'in:website,referral,campaign,import'],
        ]);

        // upsert مخاطب (idempotent با email)
        $contact = $this->contacts->syncFromExternal((string) ($validated['source'] ?? 'website'), [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
        ]);

        $lead = $this->leads->create($contact->id(), [
            'title' => (string) $validated['title'],
            'value' => $validated['value'] ?? 0,
        ]);

        return [
            'data' => [
                'lead_id' => $lead->id(),
                'contact_id' => $contact->id(),
                'status' => $lead->status()->value,
                'score' => $lead->score(),
            ],
        ];
    }

    /**
     * ورود تیکت — {name, email, subject, body, priority?}.
     *
     * @return array<string, mixed>
     */
    public function createTicket(RequestInterface $request): array
    {
        $validated = Validator::validate($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:5', 'max:20000'],
            'priority' => ['nullable', 'in:low,normal,high'],
        ]);

        $contact = $this->tickets->portalContact((string) $validated['name'], (string) $validated['email']);

        $ticket = $this->tickets->create([
            'subject' => (string) $validated['subject'],
            'body' => (string) $validated['body'],
            'priority' => (string) ($validated['priority'] ?? 'normal'),
        ], contactId: $contact?->id(), fromPortal: true);

        return [
            'data' => [
                'ticket_id' => $ticket->id(),
                'ticket_number' => $ticket->ticketNumber(),
                'status' => $ticket->status()->value,
                'sla_due_at' => $ticket->slaDueAt()?->format('c'),
            ],
        ];
    }

    /**
     * وضعیت تیکت با شماره.
     *
     * @return array<string, mixed>
     */
    public function ticketStatus(RequestInterface $request, string $number): array
    {
        $ticket = $this->ticketRepo->findByNumber($number);
        if ($ticket === null) {
            throw new RouteNotFoundException(messageId: 'crm::messages.ticket_not_found', context: ['number' => $number]);
        }

        return [
            'data' => [
                'ticket_number' => $ticket->ticketNumber(),
                'subject' => $ticket->subject(),
                'status' => $ticket->status()->value,
                'priority' => $ticket->priority()->value,
                'escalated' => $ticket->isEscalated(),
                'resolved_at' => $ticket->resolvedAt()?->format('c'),
            ],
        ];
    }
}
