<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * OpportunityWasWon — فرصت برنده شد (DOC-096 §5: report, celebration, invoice).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class OpportunityWasWon
{
    public function __construct(
        public string $opportunityId,
        public string $title,
        public string $contactId,
        public int $valueMinor,
        public string $currency,
        public ?int $ownerId,
        public ?string $leadId = null,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.opportunity.won';
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
            'lead_id' => $this->leadId,
        ];
    }
}
