<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * LeadPolicy — مالکیت سرنخ (DOC-096 §4: sales-agent روی سرنخ خود).
 */

namespace Modules\Crm\Policies;

use Modules\Crm\Domain\Entities\Lead;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * @api 1.0
 */
final class LeadPolicy
{
    public function view(Authenticatable $user, Lead $lead): bool
    {
        return $this->any($user, 'crm.lead.view') || $this->owns($user, $lead);
    }

    public function update(Authenticatable $user, Lead $lead): bool
    {
        return Gate::allows('crm.lead.update') || (Gate::allows('crm.lead.create') && $this->owns($user, $lead));
    }

    public function convert(Authenticatable $user, Lead $lead): bool
    {
        return Gate::allows('crm.lead.convert') && ($this->any($user, 'crm.lead.update') || $this->owns($user, $lead));
    }

    public function assign(Authenticatable $user, Lead $lead): bool
    {
        return Gate::allows('crm.lead.assign');
    }

    private function owns(Authenticatable $user, Lead $lead): bool
    {
        return $lead->ownerId() !== null && (int) $user->getAuthIdentifier() === $lead->ownerId();
    }

    private function any(Authenticatable $user, string $permission): bool
    {
        return Gate::allows($permission);
    }
}
