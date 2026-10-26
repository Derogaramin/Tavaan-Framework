<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ProcessWasStarted — رویداد `bpms.process.started` (DOC-098 §10).
 */

namespace Modules\Bpms\Domain\Events;

/**
 * ProcessWasStarted — نمونهٔ فرایند آغاز شد (مصرف‌کننده: notification →
 * initiator، realtime، mirror موتور workflow).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class ProcessWasStarted
{
    /** @param array<string, mixed> $variables */
    public function __construct(
        public string $instanceId,
        public string $definitionKey,
        public ?int $initiatorId,
        public ?string $businessKey,
        public array $variables,
    ) {
    }

    public function eventName(): string
    {
        return 'bpms.process.started';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'definition_key' => $this->definitionKey,
            'initiator_id' => $this->initiatorId,
            'business_key' => $this->businessKey,
        ];
    }
}
