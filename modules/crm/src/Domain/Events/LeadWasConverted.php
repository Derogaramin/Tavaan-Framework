<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * LeadWasConverted — سرنخ به فرصت فروش تبدیل شد (DOC-096 §3.1/§5).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class LeadWasConverted
{
    public function __construct(
        public string $leadId,
        public string $opportunityId,
        public string $contactId,
        public int $valueMinor,
        public string $currency,
        public int $score,
        public ?int $ownerId,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.lead.converted';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'lead_id' => $this->leadId,
            'opportunity_id' => $this->opportunityId,
            'contact_id' => $this->contactId,
            'value_minor' => $this->valueMinor,
            'currency' => $this->currency,
            'score' => $this->score,
            'owner_id' => $this->ownerId,
        ];
    }
}
