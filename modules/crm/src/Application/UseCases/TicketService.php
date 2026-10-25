<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketService — تیکت پشتیبانی با SLA (DOC-096 §3.3).
 */

namespace Modules\Crm\Application\UseCases;

use Modules\Crm\Domain\Entities\Contact;
use Modules\Crm\Domain\Entities\Ticket;
use Modules\Crm\Domain\Entities\TicketReply;
use Modules\Crm\Domain\Enums\TicketPriority;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Modules\Crm\Domain\Services\SlaPolicy;
use Modules\Crm\Domain\Services\TicketNumberGenerator;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * TicketService — جریان §3.3:
 *
 * ```
 * create (number + SLA by priority) → assign → agent reply (→ pending)
 *   → customer reply (→ open) → resolve → (confirm) → closed
 * SLA breach → escalation (SlaMonitorService)
 * ```
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class TicketService
{
    /**
     * @param array<string, mixed> $config کلید config('crm')
     */
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly ContactRepositoryInterface $contacts,
        private readonly SlaPolicy $sla,
        private readonly TicketNumberGenerator $numbers,
        private readonly array $config = [],
    ) {
    }

    /**
     * ساخت تیکت — number یکتا + sla_due_at بر اساس priority.
     *
     * @param array<string, mixed> $input
     */
    public function create(array $input, ?int $creatorId = null, ?string $contactId = null, bool $fromPortal = false): Ticket
    {
        $validated = Validator::validate($input, [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ], attributes: [
            'subject' => 'عنوان',
            'body' => 'متن',
            'priority' => 'اولویت',
        ]);

        // 🔒 متن پرتال: sanitize پروفایل plain (دفاع در عمق — escape در ویو)
        $body = $fromPortal
            ? Security::sanitize((string) $validated['body'], 'plain')
            : (string) $validated['body'];

        $priority = TicketPriority::tryFromString((string) ($validated['priority'] ?? 'normal'));

        // پرتال عمومی فقط normal/low می‌گیرد — اولویت بالا تصمیم agent است
        if ($fromPortal && \in_array($priority, [TicketPriority::Urgent, TicketPriority::High], true)) {
            $priority = TicketPriority::Normal;
        }

        $ticket = Ticket::create(
            ticketNumber: $this->uniqueNumber(),
            subject: (string) $validated['subject'],
            body: $body,
            contactId: $contactId,
            category: ($validated['category'] ?? null) === null || $validated['category'] === '' ? null : (string) $validated['category'],
            priority: $priority,
            assignedTo: isset($input['assigned_to']) && (int) $input['assigned_to'] > 0 ? (int) $input['assigned_to'] : null,
            slaDueAt: $this->sla->dueAt($priority),
        );

        $this->tickets->save($ticket);
        $this->flushEvents($ticket);   // → crm.ticket.created (SLA/assignment/notification)

        return $ticket;
    }

    /**
     * ساخت/یافتن مخاطب پرتال بر اساس ایمیل (مهمان → contact با source=support).
     */
    public function portalContact(string $name, string $email): ?Contact
    {
        $email = \mb_strtolower(\trim($email));
        if ($email === '') {
            return null;
        }

        $contact = $this->contacts->findByEmail($email);
        if ($contact !== null) {
            return $contact;
        }

        $parts = \preg_split('/\s+/', \trim($name)) ?: [];
        $contact = Contact::create(
            firstName: (string) ($parts[0] ?? '') ?: 'مهمان',
            lastName: \implode(' ', \array_slice($parts, 1)) ?: '',
            email: $email,
            source: 'support',
        );
        $this->contacts->save($contact);

        return $contact;
    }

    public function assign(Ticket $ticket, ?int $userId): Ticket
    {
        try {
            $ticket->assignTo($userId);
        } catch (\DomainException $e) {
            throw new ValidationException(errors: ['ticket' => [$e->getMessage()]], messageId: 'crm::messages.ticket_closed');
        }
        $this->tickets->save($ticket);
        $this->flushEvents($ticket);   // → crm.ticket.assigned (notification کارشناس)

        return $ticket;
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority): Ticket
    {
        try {
            $ticket->changePriority($priority, $this->sla->dueAt($priority));
        } catch (\DomainException $e) {
            throw new ValidationException(errors: ['ticket' => [$e->getMessage()]], messageId: 'crm::messages.ticket_closed');
        }
        $this->tickets->save($ticket);

        return $ticket;
    }

    /**
     * پاسخ کارشناس — status → pending (منتظر مشتری؛ توقف SLA — §3.3).
     *
     * @param list<string> $attachments
     */
    public function agentReply(Ticket $ticket, string $body, int $agentId, bool $isInternal = false, array $attachments = []): TicketReply
    {
        $body = \trim($body);
        if ($body === '' || \mb_strlen($body) > 20000) {
            throw new ValidationException(
                errors: ['body' => ['متن پاسخ باید بین ۱ و ۲۰۰۰۰ نویسه باشد.']],
                messageId: 'crm::messages.reply_body_invalid',
            );
        }

        $reply = TicketReply::create(
            ticketId: $ticket->id(),
            body: Security::sanitize($body, 'plain'),
            authorType: 'agent',
            authorId: $agentId,
            attachments: $attachments,
            isInternal: $isInternal,
        );
        $this->tickets->saveReply($reply);

        // یادداشت داخلی وضعیت را عوض نمی‌کند
        if (!$isInternal && $ticket->status()->canTransitionTo(\Modules\Crm\Domain\Enums\TicketStatus::Pending)) {
            $ticket->markAwaitingCustomer();
            $this->tickets->save($ticket);
        }

        return $reply;
    }

    /**
     * پاسخ مشتری (پرتال) — pending → open (ادامهٔ SLA).
     */
    public function customerReply(Ticket $ticket, string $body, ?int $authorId = null): TicketReply
    {
        $body = \trim($body);
        if ($body === '' || \mb_strlen($body) > 20000) {
            throw new ValidationException(
                errors: ['body' => ['متن پاسخ باید بین ۱ و ۲۰۰۰۰ نویسه باشد.']],
                messageId: 'crm::messages.reply_body_invalid',
            );
        }

        $reply = TicketReply::create(
            ticketId: $ticket->id(),
            body: Security::sanitize($body, 'plain'),
            authorType: 'customer',
            authorId: $authorId,
        );
        $this->tickets->saveReply($reply);

        if ($ticket->status() === \Modules\Crm\Domain\Enums\TicketStatus::Resolved) {
            // مشتری حل را نپذیرفته → reopen
            $ticket->reopen();
        } else {
            $ticket->reopenByCustomer();
        }
        $this->tickets->save($ticket);

        return $reply;
    }

    public function resolve(Ticket $ticket, ?int $resolverId = null): Ticket
    {
        try {
            $ticket->resolve($resolverId);
        } catch (\DomainException $e) {
            throw new ValidationException(errors: ['ticket' => [$e->getMessage()]], messageId: 'crm::messages.ticket_state_invalid');
        }
        $this->tickets->save($ticket);
        $this->flushEvents($ticket);   // → crm.ticket.resolved (satisfaction survey)

        return $ticket;
    }

    public function close(Ticket $ticket): Ticket
    {
        try {
            $ticket->close();
        } catch (\DomainException $e) {
            throw new ValidationException(errors: ['ticket' => [$e->getMessage()]], messageId: 'crm::messages.ticket_state_invalid');
        }
        $this->tickets->save($ticket);

        return $ticket;
    }

    /** پاسخ‌ها — internal فقط برای agentها. @return list<TicketReply> */
    public function replies(Ticket $ticket, bool $asAgent = true): array
    {
        return $this->tickets->repliesOf($ticket->id(), $asAgent);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function uniqueNumber(): string
    {
        do {
            $number = $this->numbers->generate();
        } while ($this->tickets->numberExists($number));

        return $number;
    }

    private function flushEvents(Ticket $ticket): void
    {
        foreach ($ticket->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
