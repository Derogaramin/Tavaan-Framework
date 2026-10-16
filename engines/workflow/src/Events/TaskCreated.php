<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Events;

/**
 * تَوان | Tavaan — Workflow
 * TaskCreated — کار انسانی ساخته شد (`workflow.task.created`).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class TaskCreated
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $instanceId,
        public readonly string $stateId,
        public readonly ?string $assigneeType = null,
        public readonly ?string $assigneeId = null,
    ) {
    }
}
