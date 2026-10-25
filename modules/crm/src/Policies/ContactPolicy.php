<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ContactPolicy — دسترسی مخاطب (DOC-096 §4).
 */

namespace Modules\Crm\Policies;

use Modules\Crm\Domain\Entities\Contact;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * @api 1.0
 */
final class ContactPolicy
{
    public function view(Authenticatable $user, Contact $contact): bool
    {
        return Gate::allows('crm.contact.view') || $this->owns($user, $contact);
    }

    public function update(Authenticatable $user, Contact $contact): bool
    {
        return Gate::allows('crm.contact.update') || (Gate::allows('crm.contact.create') && $this->owns($user, $contact));
    }

    public function delete(Authenticatable $user, Contact $contact): bool
    {
        return Gate::allows('crm.contact.delete');
    }

    public function assign(Authenticatable $user, Contact $contact): bool
    {
        return Gate::allows('crm.contact.assign');
    }

    private function owns(Authenticatable $user, Contact $contact): bool
    {
        return $contact->ownerId() !== null && (int) $user->getAuthIdentifier() === $contact->ownerId();
    }
}
