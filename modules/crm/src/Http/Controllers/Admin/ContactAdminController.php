<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ContactAdminController — مدیریت مخاطبان (/admin/crm/contacts).
 */

namespace Modules\Crm\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Application\UseCases\ContactService;
use Modules\Crm\Domain\Entities\Contact;
use Modules\Crm\Domain\Repositories\ActivityRepositoryInterface;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Repositories\LeadRepositoryInterface;
use Modules\Crm\Domain\Repositories\OpportunityRepositoryInterface;
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
final class ContactAdminController
{
    public function __construct(
        private readonly ContactService $service,
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
        $this->authorize('crm.contact.view', own: 'crm.contact.create');

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
        if (!Gate::allows('crm.contact.view')) {
            $filters['owner_id'] = (int) Auth::id();
        }

        $result = $this->contacts->paginate($filters, $page, 20);

        return View::render('crm::admin.contacts-index', [
            'contacts' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'query' => (string) $request->input('q', ''),
            'owners' => $this->ownerNames($result['rows']),
        ]);
    }

    public function create(): mixed
    {
        $this->authorize('crm.contact.create');

        return View::render('crm::admin.contact-form', ['contact' => null]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize('crm.contact.create');

        try {
            $contact = $this->service->create($request->all(), (int) Auth::id());
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'contact_created');

        return redirect('/admin/crm/contacts/' . $contact->id());
    }

    public function show(string $id): mixed
    {
        $contact = $this->findOr404($id);
        $this->authorizeModel('view', $contact);

        return View::render('crm::admin.contact-show', [
            'contact' => $contact,
            'leads' => $this->leads->forContact($contact->id()),
            'opportunities' => $this->opportunities->forContact($contact->id()),
            'tickets' => $this->tickets->forContact($contact->id()),
            'activities' => $this->activities->forSubject(contactId: $contact->id(), limit: 50),
            'ownerName' => $contact->ownerId() !== null ? $this->users->displayNameOf($contact->ownerId(), '—') : null,
            'nationalIdMasked' => ContactService::maskNationalId($contact->nationalId()),
            'users' => $this->assignableUsers(),
        ]);
    }

    public function edit(string $id): mixed
    {
        $contact = $this->findOr404($id);
        $this->authorizeModel('update', $contact);

        return View::render('crm::admin.contact-form', ['contact' => $contact]);
    }

    public function update(RequestInterface $request, string $id): mixed
    {
        $contact = $this->findOr404($id);
        $this->authorizeModel('update', $contact);

        try {
            $this->service->update($contact, $request->all());
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'contact_updated');

        return redirect('/admin/crm/contacts/' . $contact->id());
    }

    public function assign(RequestInterface $request, string $id): mixed
    {
        $contact = $this->findOr404($id);
        $this->authorizeModel('assign', $contact);

        $ownerId = (int) $request->input('owner_id', 0);
        $this->service->assignOwner($contact, $ownerId > 0 ? $ownerId : null);

        Session::flash('status', 'contact_assigned');

        return back();
    }

    public function destroy(string $id): mixed
    {
        $contact = $this->findOr404($id);
        $this->authorizeModel('delete', $contact);

        $this->service->delete($contact);
        Session::flash('status', 'contact_deleted');

        return redirect('/admin/crm/contacts');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): Contact
    {
        $contact = $this->contacts->findById($id);
        if ($contact === null) {
            throw new RouteNotFoundException(messageId: 'crm::messages.contact_not_found', context: ['id' => $id]);
        }

        return $contact;
    }

    /** @param list<Contact> $contacts @return array<string, string> */
    private function ownerNames(array $contacts): array
    {
        $names = [];
        foreach ($contacts as $contact) {
            if ($contact->ownerId() !== null && !isset($names[(string) $contact->ownerId()])) {
                $names[(string) $contact->ownerId()] = $this->users->displayNameOf($contact->ownerId(), '—');
            }
        }

        return $names;
    }

    /** فهرست کاربران قابل تخصیص (best-effort از جداول RBAC). @return list<array{id: int, name: string}> */
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
