<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * CampaignWasSent — کمپین ارسال شد (DOC-096 §2).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class CampaignWasSent
{
    public function __construct(
        public string $campaignId,
        public string $name,
        public string $type,
        public int $sentCount,
        public int $failedCount,
    ) {
    }

    public function eventName(): string
    {
        return 'crm.campaign.sent';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'name' => $this->name,
            'type' => $this->type,
            'sent_count' => $this->sentCount,
            'failed_count' => $this->failedCount,
        ];
    }
}
