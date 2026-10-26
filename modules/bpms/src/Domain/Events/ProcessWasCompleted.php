<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ProcessWasCompleted — رویداد `bpms.process.completed` (DOC-098 §10).
 */

namespace Modules\Bpms\Domain\Events;

/**
 * ProcessWasCompleted — فرایند به پایان رسید (مصرف‌کننده: notification →
 * initiator، report/پایش).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class ProcessWasCompleted
{
    public function __construct(
        public string $instanceId,
        public string $definitionKey,
        public ?int $initiatorId,
        public ?string $businessKey,
        public ?int $durationMs,
    ) {
    }

    public function eventName(): string
    {
        return 'bpms.process.completed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'definition_key' => $this->definitionKey,
            'initiator_id' => $this->initiatorId,
            'business_key' => $this->businessKey,
            'duration_ms' => $this->durationMs,
        ];
    }
}
