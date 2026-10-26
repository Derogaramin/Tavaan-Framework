<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskWasEscalated — رویداد `bpms.task.escalated` (DOC-098 §9/§10).
 */

namespace Modules\Bpms\Domain\Events;

/**
 * TaskWasEscalated — کار از سررسید گذشت و escalate شد (مصرف‌کننده:
 * notification → مدیر/نقش escalation، پایش SLA).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class TaskWasEscalated
{
    public function __construct(
        public string $taskId,
        public string $instanceId,
        public string $stateId,
        public string $title,
        public string $escalatedTo,
        public string $action,
    ) {
    }

    public function eventName(): string
    {
        return 'bpms.task.escalated';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'task_id' => $this->taskId,
            'instance_id' => $this->instanceId,
            'state_id' => $this->stateId,
            'title' => $this->title,
            'escalated_to' => $this->escalatedTo,
            'action' => $this->action,
        ];
    }
}
