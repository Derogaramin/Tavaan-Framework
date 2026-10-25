<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * OpportunityAdminController — pipeline فرصت‌ها (/admin/crm/opportunities).
 */

namespace Modules\Crm\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Application\UseCases\PipelineService;
use Modules\Crm\Domain\Entities\Opportunity;
use Modules\Crm\Domain\Enums\OpportunityStage;
use Modules\Crm\Domain\Repositories\ActivityRepositoryInterface;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Repositories\OpportunityRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class OpportunityAdminController
{
    public function __construct(
        private readonly PipelineService $pipeline,
        private readonly OpportunityRepositoryInterface $opportunities,
        private readonly ContactRepositoryInterface $contacts,
        private readonly ActivityRepositoryInterface $activities,
        private readonly UserReaderInterface $users,
    ) {
    }

    /** فهرست + نمای board بر اساس stage. */
    public function index(RequestInterface $request): mixed
    {
        $this->authorize('crm.opportunity.view', own: 'crm.opportunity.create');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($stage = (string) $request->input('stage', '')) !== '') {
            $filters['stage'] = $stage;
        }
        if (($q = (string) $request->input('q', '')) !== '') {
            $filters['search'] = $q;
        }
        if (!Gate::allows('crm.opportunity.view')) {
            $filters['owner_id'] = (int) Auth::id();
        }

        $result = $this->opportunities->paginate($filters, $page, 20);

        $forecast = Gate::allows('crm.report.view') || Gate::allows('crm.opportunity.view') ? $this->pipeline->forecast() : null;

        return View::render('crm::admin.opportunities-index', [
            'opportunities' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'query' => (string) $request->input('q', ''),
            'stageFilter' => (string) $request->input('stage', ''),
            'forecast' => $forecast,
            'contacts' => $this->contactNames($result['rows']),
        ]);
    }

    public function create(RequestInterface $request): mixed
    {
        $this->authorize('crm.opportunity.create');

        return View::render('crm::admin.opportunity-form', [
            'opportunity' => null,
            'contacts' => $this->contacts->paginate(['status' => 'active'], 1, 100)['rows'],
            'activities' => [],
        ]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize('crm.opportunity.create');

        $validated = Validator::validate($request->all(), [
            'contact_id' => ['required', 'string', 'max:26'],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'integer', 'min:0'],
            'expected_close_date' => ['nullable', 'string', 'max:40'],
        ], attributes: ['contact_id' => 'مخاطب', 'title' => 'عنوان فرصت', 'value' => 'ارزش']);

        try {
            $opportunity = $this->pipeline->create(
                contactId: (string) $validated['contact_id'],
                title: (string) $validated['title'],
                valueMinor: (int) ($validated['value'] ?? 0),
                ownerId: (int) Auth::id(),
                expectedCloseDate: ($validated['expected_close_date'] ?? '') === '' ? null : (string) $validated['expected_close_date'],
            );
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'opportunity_created');

        return redirect('/admin/crm/opportunities/' . $opportunity->id());
    }

    public function show(string $id): mixed
    {
        $opportunity = $this->findOr404($id);
        $this->authorizeModel('view', $opportunity);

        return View::render('crm::admin.opportunity-show', [
            'opportunity' => $opportunity,
            'contact' => $this->contacts->findById($opportunity->contactId()),
            'activities' => $this->activities->forSubject(opportunityId: $opportunity->id(), limit: 50),
            'ownerName' => $opportunity->ownerId() !== null ? $this->users->displayNameOf($opportunity->ownerId(), '—') : null,
            'users' => $this->assignableUsers(),
        ]);
    }

    public function update(RequestInterface $request, string $id): mixed
    {
        $opportunity = $this->findOr404($id);
        $this->authorizeModel('update', $opportunity);

        try {
            $this->pipeline->revise(
                $opportunity,
                (string) $request->input('title', $opportunity->title()),
                $request->input('value') === null || $request->input('value') === '' ? null : (int) $request->input('value'),
                (string) $request->input('expected_close_date', '') === '' ? null : (string) $request->input('expected_close_date', ''),
            );
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'opportunity_updated');

        return back();
    }

    /** جابجایی مرحله (win/lose/stage) — DOC-096 §3.1. */
    public function move(RequestInterface $request, string $id): mixed
    {
        $opportunity = $this->findOr404($id);
        $this->authorizeModel('move', $opportunity);

        $stageValue = (string) $request->input('stage', '');
        $target = OpportunityStage::tryFrom($stageValue);
        if ($target === null) {
            Session::flash('error', 'مرحلهٔ نامعتبر.');

            return back();
        }

        try {
            if ($target === OpportunityStage::ClosedLost) {
                $this->pipeline->lose($opportunity, (string) $request->input('lost_reason', ''));
            } elseif ($target === OpportunityStage::ClosedWon) {
                $this->pipeline->win($opportunity);
            } else {
                $this->pipeline->moveToStage($opportunity, $target);
            }
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', $target->isWon() ? 'opportunity_won' : ($target === OpportunityStage::ClosedLost ? 'opportunity_lost' : 'stage_moved'));

        return back();
    }

    public function assign(RequestInterface $request, string $id): mixed
    {
        $opportunity = $this->findOr404($id);
        $this->authorizeModel('assign', $opportunity);

        $ownerId = (int) $request->input('owner_id', 0);
        $this->pipeline->assign($opportunity, $ownerId > 0 ? $ownerId : null);

        Session::flash('status', 'opportunity_assigned');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): Opportunity
    {
        $opportunity = $this->opportunities->findById($id);
        if ($opportunity === null) {
            throw new RouteNotFoundException(messageId: 'crm::messages.opportunity_not_found', context: ['id' => $id]);
        }

        return $opportunity;
    }

    /** @param list<Opportunity> $rows @return array<string, string> */
    private function contactNames(array $rows): array
    {
        $names = [];
        foreach ($rows as $opportunity) {
            if (!isset($names[$opportunity->contactId()])) {
                $contact = $this->contacts->findById($opportunity->contactId());
                $names[$opportunity->contactId()] = $contact !== null ? $contact->fullName() : '—';
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

    private function authorize(string $permission, ?string $own = null): void
    {
        if (Gate::allows($permission)) {
            return;
        }
        if ($own !== null && Gate::allows($own)) {
            return;
        }

        throw new ForbiddenException(messageId: 'crm::messages.forbidden', context: ['permission' => $permission]);
    }

    private function authorizeModel(string $ability, object $model): void
    {
        if (!Gate::allows($ability, $model)) {
            throw new ForbiddenException(messageId: 'crm::messages.forbidden', context: ['ability' => $ability]);
        }
    }
}
