<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskWasCreated — رویداد `bpms.task.created` (DOC-098 §10).
 */

namespace Modules\Bpms\Domain\Events;

/**
 * TaskWasCreated — کار انسانی ساخته شد (مصرف‌کننده: notification →
 * assignee، realtime/SSE «کار جدید» — §8).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class TaskWasCreated
{
    public function __construct(
        public string $taskId,
        public string $instanceId,
        public string $stateId,
        public string $title,
        public string $assigneeType,
        public ?string $assigneeId,
        public ?string $dueAt,
        public ?int $initiatorId,
    ) {
    }

    public function eventName(): string
    {
        return 'bpms.task.created';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'task_id' => $this->taskId,
            'instance_id' => $this->instanceId,
            'state_id' => $this->stateId,
            'title' => $this->title,
            'assignee_type' => $this->assigneeType,
            'assignee_id' => $this->assigneeId,
            'due_at' => $this->dueAt,
            'initiator_id' => $this->initiatorId,
        ];
    }
}
