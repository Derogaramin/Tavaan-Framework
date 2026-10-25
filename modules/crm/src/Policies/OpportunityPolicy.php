<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * OpportunityPolicy — مالکیت فرصت فروش (DOC-096 §4).
 */

namespace Modules\Crm\Policies;

use Modules\Crm\Domain\Entities\Opportunity;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * @api 1.0
 */
final class OpportunityPolicy
{
    public function view(Authenticatable $user, Opportunity $opportunity): bool
    {
        return Gate::allows('crm.opportunity.view') || $this->owns($user, $opportunity);
    }

    public function update(Authenticatable $user, Opportunity $opportunity): bool
    {
        return Gate::allows('crm.opportunity.update') || (Gate::allows('crm.opportunity.create') && $this->owns($user, $opportunity));
    }

    /** جابجایی مرحلهٔ pipeline. */
    public function move(Authenticatable $user, Opportunity $opportunity): bool
    {
        return Gate::allows('crm.opportunity.move') || (Gate::allows('crm.opportunity.create') && $this->owns($user, $opportunity));
    }

    public function assign(Authenticatable $user, Opportunity $opportunity): bool
    {
        return Gate::allows('crm.opportunity.assign');
    }

    private function owns(Authenticatable $user, Opportunity $opportunity): bool
    {
        return $opportunity->ownerId() !== null && (int) $user->getAuthIdentifier() === $opportunity->ownerId();
    }
}
