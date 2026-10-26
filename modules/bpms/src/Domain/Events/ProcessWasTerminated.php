<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ProcessWasTerminated — رویداد `bpms.process.terminated` (افزودنی بر §10).
 */

namespace Modules\Bpms\Domain\Events;

/**
 * ProcessWasTerminated — نمونه با تصمیم مدیریتی متوقف شد (audit +
 * notification → initiator). افزودنی مستند‌شده بر جدول §10 (پایان غیرطبیعی
 * از completed تفکیک می‌شود — §2 status=terminated).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class ProcessWasTerminated
{
    public function __construct(
        public string $instanceId,
        public string $definitionKey,
        public ?int $initiatorId,
        public int $actorId,
        public ?string $reason,
    ) {
    }

    public function eventName(): string
    {
        return 'bpms.process.terminated';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'definition_key' => $this->definitionKey,
            'initiator_id' => $this->initiatorId,
            'actor_id' => $this->actorId,
            'reason' => $this->reason,
        ];
    }
}
