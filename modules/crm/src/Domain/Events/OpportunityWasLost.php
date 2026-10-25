<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * OpportunityWasLost — فرصت از دست رفت (با دلیل).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class OpportunityWasLost
{
    public function __construct(
        public string $opportunityId,
        public string $title,
        public string $contactId,
        public int $valueMinor,
        public string $currency,
        public ?int $ownerId,
        public string $reason,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.opportunity.lost';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'opportunity_id' => $this->opportunityId,
            'title' => $this->title,
            'contact_id' => $this->contactId,
            'value_minor' => $this->valueMinor,
            'currency' => $this->currency,
            'owner_id' => $this->ownerId,
            'reason' => $this->reason,
        ];
    }
}
