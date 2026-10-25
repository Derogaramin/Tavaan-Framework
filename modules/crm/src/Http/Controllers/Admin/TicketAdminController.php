<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketAdminController — مدیریت تیکت‌ها (/admin/crm/tickets).
 */

namespace Modules\Crm\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Application\UseCases\TicketService;
use Modules\Crm\Domain\Entities\Ticket;
use Modules\Crm\Domain\Enums\TicketPriority;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class TicketAdminController
{
    public function __construct(
        private readonly TicketService $service,
        private readonly TicketRepositoryInterface $tickets,
        private readonly ContactRepositoryInterface $contacts,
        private readonly UserReaderInterface $users,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        // support-agent بدون crm.ticket.view → فقط assignedهای خود
        $canViewAll = Gate::allows('crm.ticket.view');
        if (!$canViewAll && !Gate::allows('crm.ticket.reply')) {
            throw new ForbiddenException(messageId: 'crm::messages.forbidden', context: ['permission' => 'crm.ticket.view']);
        }

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        foreach (['status', 'priority'] as $key) {
            if (($value = (string) $request->input($key, '')) !== '') {
                $filters[$key] = $value;
            }
        }
        if (($q = (string) $request->input('q', '')) !== '') {
            $filters['search'] = $q;
        }
        if ($request->input('escalated') === '1') {
            $filters['escalated'] = true;
        }
        if (!$canViewAll) {
            $filters['mine'] = (int) Auth::id();
        }

        $result = $this->tickets->paginate($filters, $page, 20);

        return View::render('crm::admin.tickets-index', [
            'tickets' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'query' => (string) $request->input('q', ''),
            'statusFilter' => (string) $request->input('status', ''),
            'priorityFilter' => (string) $request->input('priority', ''),
            'escalatedOnly' => $request->input('escalated') === '1',
            'assignees' => $this->assigneeNames($result['rows']),
        ]);
    }

    public function show(string $id): mixed
    {
        $ticket = $this->findOr404($id);
        $this->authorizeModel('view', $ticket);

        return View::render('crm::admin.ticket-show', [
            'ticket' => $ticket,
            'replies' => $this->service->replies($ticket, asAgent: true),
            'contact' => $ticket->contactId() !== null ? $this->contacts->findById($ticket->contactId()) : null,
            'assigneeName' => $ticket->assignedTo() !== null ? $this->users->displayNameOf($ticket->assignedTo(), '—') : null,
            'users' => $this->assignableUsers(),
            'slaMinutesLeft' => $ticket->slaMinutesLeft(),
        ]);
    }

    public function assign(RequestInterface $request, string $id): mixed
    {
        $ticket = $this->findOr404($id);
        $this->authorizeModel('assign', $ticket);

        $userId = (int) $request->input('assigned_to', 0);

        try {
            $this->service->assign($ticket, $userId > 0 ? $userId : null);
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'ticket_assigned');

        return back();
    }

    public function priority(RequestInterface $request, string $id): mixed
    {
        $ticket = $this->findOr404($id);
        $this->authorizeModel('assign', $ticket);

        try {
            $this->service->changePriority($ticket, TicketPriority::tryFromString((string) $request->input('priority', 'normal')));
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'ticket_priority_changed');

        return back();
    }

    /** پاسخ agent (پاسخ عمومی یا یادداشت داخلی). */
    public function reply(RequestInterface $request, string $id): mixed
    {
        $ticket = $this->findOr404($id);
        $this->authorizeModel('reply', $ticket);

        try {
            $this->service->agentReply(
                $ticket,
                (string) $request->input('body', ''),
                (int) Auth::id(),
                isInternal: $request->input('internal') !== null,
            );
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'reply_added');

        return back();
    }

    public function resolve(string $id): mixed
    {
        $ticket = $this->findOr404($id);
        $this->authorizeModel('resolve', $ticket);

        try {
            $this->service->resolve($ticket, (int) Auth::id());
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'ticket_resolved');

        return back();
    }

    public function close(string $id): mixed
    {
        $ticket = $this->findOr404($id);
        $this->authorizeModel('resolve', $ticket);

        try {
            $this->service->close($ticket);
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'ticket_closed');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): Ticket
    {
        $ticket = $this->tickets->findById($id);
        if ($ticket === null) {
            throw new RouteNotFoundException(messageId: 'crm::messages.ticket_not_found', context: ['id' => $id]);
        }

        return $ticket;
    }

    /** @param list<Ticket> $tickets @return array<int, string> */
    private function assigneeNames(array $tickets): array
    {
        $names = [];
        foreach ($tickets as $ticket) {
            $assignee = $ticket->assignedTo();
            if ($assignee !== null && !isset($names[$assignee])) {
                $names[$assignee] = $this->users->displayNameOf($assignee, '—');
            }
        }

        return $names;
    }

    /** @return list<array{id: int, name: string}> */
    private function assignableUsers(): array
    {
        try {
            $rows = \Tavaan\Core\Facade\Facades\DB::table('users')->orderBy('name')->limit(200)->get();

            return \array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']], $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    private function authorizeModel(string $ability, object $model): void
    {
        if (!Gate::allows($ability, $model)) {
            throw new ForbiddenException(messageId: 'crm::messages.forbidden', context: ['ability' => $ability]);
        }
    }
}
