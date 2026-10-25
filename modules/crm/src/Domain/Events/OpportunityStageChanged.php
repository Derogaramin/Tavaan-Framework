<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * OpportunityStageChanged — جابجایی مرحلهٔ pipeline (DOC-096 §5: forecast update).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class OpportunityStageChanged
{
    public function __construct(
        public string $opportunityId,
        public string $title,
        public string $fromStage,
        public string $toStage,
        public int $probability,
        public int $valueMinor,
        public string $currency,
        public ?int $ownerId,
        public ?string $workflowInstanceId = null,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.opportunity.stage_changed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'opportunity_id' => $this->opportunityId,
            'title' => $this->title,
            'from_stage' => $this->fromStage,
            'to_stage' => $this->toStage,
            'probability' => $this->probability,
            'value_minor' => $this->valueMinor,
            'currency' => $this->currency,
            'owner_id' => $this->ownerId,
            'workflow_instance_id' => $this->workflowInstanceId,
        ];
    }
}
