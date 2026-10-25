<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * LeadWasCreated — سرنخ ساخته شد (DOC-096 §5: scoring, assignment, notification).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class LeadWasCreated
{
    public function __construct(
        public string $leadId,
        public string $contactId,
        public string $title,
        public int $valueMinor,
        public string $currency,
        public string $source,
        public ?int $ownerId,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.lead.created';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'lead_id' => $this->leadId,
            'contact_id' => $this->contactId,
            'title' => $this->title,
            'value_minor' => $this->valueMinor,
            'currency' => $this->currency,
            'source' => $this->source,
            'owner_id' => $this->ownerId,
        ];
    }
}
