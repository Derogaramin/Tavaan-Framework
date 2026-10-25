<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * DashboardController — داشبورد CRM (/admin/crm).
 */

namespace Modules\Crm\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Application\UseCases\ActivityService;
use Modules\Crm\Domain\Repositories\ActivityRepositoryInterface;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Repositories\LeadRepositoryInterface;
use Modules\Crm\Domain\Repositories\OpportunityRepositoryInterface;
use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class DashboardController
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly LeadRepositoryInterface $leads,
        private readonly OpportunityRepositoryInterface $opportunities,
        private readonly TicketRepositoryInterface $tickets,
        private readonly ActivityRepositoryInterface $activities,
        private readonly UserReaderInterface $users,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorizeAny(['crm.contact.view', 'crm.lead.view', 'crm.ticket.view', 'crm.opportunity.view', 'crm.campaign.view', 'crm.report.view']);

        $me = (int) Auth::id();

        $forecast = null;
        if (Gate::allows('crm.report.view')) {
            /** @var \Modules\Crm\Application\UseCases\PipelineService $pipeline */
            $pipeline = \app()->make(\Modules\Crm\Application\UseCases\PipelineService::class);
            $forecast = $pipeline->forecast();
        }

        $openLeads = $this->leads->paginate(['status' => 'new'], 1, 5);
        $myTasks = $this->activities->dueFor($me);
        $ticketStats = Gate::allows('crm.ticket.view') ? $this->tickets->stats() : null;

        return View::render('crm::admin.dashboard', [
            'contactsCount' => Gate::allows('crm.contact.view') ? $this->contacts->countAll() : null,
            'newLeads' => $openLeads['rows'],
            'forecast' => $forecast,
            'myTasks' => $myTasks,
            'ticketStats' => $ticketStats,
            'stageCounts' => Gate::allows('crm.opportunity.view') ? $this->opportunities->countByStage() : [],
            'me' => $this->users->displayNameOf($me, 'کاربر'),
        ]);
    }

    /** @param list<string> $permissions */
    private function authorizeAny(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (Gate::allows($permission)) {
                return;
            }
        }

        throw new ForbiddenException(messageId: 'crm::messages.forbidden', context: ['permissions' => \implode('|', $permissions)]);
    }
}
