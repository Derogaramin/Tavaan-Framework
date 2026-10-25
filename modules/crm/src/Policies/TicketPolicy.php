<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketPolicy — دسترسی تیکت (DOC-096 §4: support-agent روی assigned).
 */

namespace Modules\Crm\Policies;

use Modules\Crm\Domain\Entities\Ticket;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * @api 1.0
 */
final class TicketPolicy
{
    public function view(Authenticatable $user, Ticket $ticket): bool
    {
        return Gate::allows('crm.ticket.view') || $this->isAssignee($user, $ticket);
    }

    public function reply(Authenticatable $user, Ticket $ticket): bool
    {
        return Gate::allows('crm.ticket.reply') && (Gate::allows('crm.ticket.view') || $this->isAssignee($user, $ticket));
    }

    public function resolve(Authenticatable $user, Ticket $ticket): bool
    {
        return Gate::allows('crm.ticket.resolve') && (Gate::allows('crm.ticket.view') || $this->isAssignee($user, $ticket));
    }

    public function assign(Authenticatable $user, Ticket $ticket): bool
    {
        return Gate::allows('crm.ticket.assign');
    }

    private function isAssignee(Authenticatable $user, Ticket $ticket): bool
    {
        return $ticket->assignedTo() !== null && (int) $user->getAuthIdentifier() === $ticket->assignedTo();
    }
}
