<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskWasCompleted — رویداد `bpms.task.completed` (DOC-098 §10).
 */

namespace Modules\Bpms\Domain\Events;

/**
 * TaskWasCompleted — کار انجام شد (مصرف‌کننده: advance موتور، history،
 * notification، realtime).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class TaskWasCompleted
{
    public function __construct(
        public string $taskId,
        public string $instanceId,
        public string $stateId,
        public int $actorId,
        public ?int $durationMs,
        public bool $onTime,
    ) {
    }

    public function eventName(): string
    {
        return 'bpms.task.completed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'task_id' => $this->taskId,
            'instance_id' => $this->instanceId,
            'state_id' => $this->stateId,
            'actor_id' => $this->actorId,
            'duration_ms' => $this->durationMs,
            'on_time' => $this->onTime,
        ];
    }
}
