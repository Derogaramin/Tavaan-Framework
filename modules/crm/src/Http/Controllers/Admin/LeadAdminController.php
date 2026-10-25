<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * LeadAdminController — مدیریت سرنخ‌ها (/admin/crm/leads).
 */

namespace Modules\Crm\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Application\UseCases\LeadService;
use Modules\Crm\Domain\Entities\Lead;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Repositories\LeadRepositoryInterface;
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
final class LeadAdminController
{
    public function __construct(
        private readonly LeadService $service,
        private readonly LeadRepositoryInterface $leads,
        private readonly ContactRepositoryInterface $contacts,
        private readonly UserReaderInterface $users,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('crm.lead.view', own: 'crm.lead.create');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        foreach (['status', 'source'] as $key) {
            if (($value = (string) $request->input($key, '')) !== '') {
                $filters[$key] = $value;
            }
        }
        if (($q = (string) $request->input('q', '')) !== '') {
            $filters['search'] = $q;
        }
        if (($minScore = $request->input('min_score')) !== null && $minScore !== '') {
            $filters['min_score'] = (int) $minScore;
        }
        // sales-agent بدون crm.lead.view → فقط سرنخ‌های خود
        if (!Gate::allows('crm.lead.view')) {
            $filters['owner_id'] = (int) Auth::id();
        }

        $result = $this->leads->paginate($filters, $page, 20);

        return View::render('crm::admin.leads-index', [
            'leads' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'query' => (string) $request->input('q', ''),
            'statusFilter' => (string) $request->input('status', ''),
            'contacts' => $this->contactNames($result['rows']),
        ]);
    }

    public function create(RequestInterface $request): mixed
    {
        $this->authorize('crm.lead.create');

        return View::render('crm::admin.lead-form', [
            'lead' => null,
            'contacts' => $this->contacts->paginate(['status' => 'active'], 1, 100)['rows'],
            'preselectContact' => (string) $request->input('contact_id', ''),
        ]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize('crm.lead.create');

        $contactId = (string) $request->input('contact_id', '');

        try {
            $lead = $this->service->create($contactId, $request->all(), (int) Auth::id());
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'lead_created');

        return redirect('/admin/crm/leads/' . $lead->id());
    }

    public function show(string $id): mixed
    {
        $lead = $this->findOr404($id);
        $this->authorizeModel('view', $lead);

        $contact = $this->contacts->findById($lead->contactId());

        return View::render('crm::admin.lead-show', [
            'lead' => $lead,
            'contact' => $contact,
            'opportunity' => $lead->convertedTo() !== null ? null : null,   // در ویو از leads->convertedTo لینک می‌شود
            'users' => $this->assignableUsers(),
        ]);
    }

    public function update(RequestInterface $request, string $id): mixed
    {
        $lead = $this->findOr404($id);
        $this->authorizeModel('update', $lead);

        try {
            $this->service->update($lead, $request->all());
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'lead_updated');

        return back();
    }

    public function qualify(string $id): mixed
    {
        $lead = $this->findOr404($id);
        $this->authorizeModel('update', $lead);

        try {
            $this->service->qualify($lead);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'lead_qualified');

        return back();
    }

    public function convert(RequestInterface $request, string $id): mixed
    {
        $lead = $this->findOr404($id);
        $this->authorizeModel('convert', $lead);

        try {
            $result = $this->service->convert($lead, [
                'title' => (string) $request->input('title', $lead->title()),
                'value' => $request->input('value') === null || $request->input('value') === '' ? null : (int) $request->input('value'),
                'expected_close_date' => (string) $request->input('expected_close_date', ''),
            ], (int) Auth::id());
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'lead_converted');

        return redirect('/admin/crm/opportunities/' . $result['opportunity']->id());
    }

    public function lost(RequestInterface $request, string $id): mixed
    {
        $lead = $this->findOr404($id);
        $this->authorizeModel('update', $lead);

        try {
            $this->service->markLost($lead, (string) $request->input('reason', ''));
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'lead_lost');

        return back();
    }

    public function assign(RequestInterface $request, string $id): mixed
    {
        $lead = $this->findOr404($id);
        $this->authorizeModel('assign', $lead);

        $ownerId = (int) $request->input('owner_id', 0);
        if ($ownerId <= 0) {
            Session::flash('error', 'کارشناس معتبر انتخاب کنید.');

            return back();
        }

        $this->service->assign($lead, $ownerId);
        Session::flash('status', 'lead_assigned');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): Lead
    {
        $lead = $this->leads->findById($id);
        if ($lead === null) {
            throw new RouteNotFoundException(messageId: 'crm::messages.lead_not_found', context: ['id' => $id]);
        }

        return $lead;
    }

    /** @param list<Lead> $leads @return array<string, string> contactId → نام */
    private function contactNames(array $leads): array
    {
        $names = [];
        foreach ($leads as $lead) {
            if (!isset($names[$lead->contactId()])) {
                $contact = $this->contacts->findById($lead->contactId());
                $names[$lead->contactId()] = $contact !== null ? $contact->fullName() : '—';
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
