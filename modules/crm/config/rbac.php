<?php

declare(strict_types=1);

/*
 * ماژول CRM — نگاشت Policyهای RBAC (ادغام در rbac.policies).
 */

return [
    'policies' => [
        \Modules\Crm\Domain\Entities\Lead::class => \Modules\Crm\Policies\LeadPolicy::class,
        \Modules\Crm\Domain\Entities\Opportunity::class => \Modules\Crm\Policies\OpportunityPolicy::class,
        \Modules\Crm\Domain\Entities\Ticket::class => \Modules\Crm\Policies\TicketPolicy::class,
        \Modules\Crm\Domain\Entities\Contact::class => \Modules\Crm\Policies\ContactPolicy::class,
    ],
];
