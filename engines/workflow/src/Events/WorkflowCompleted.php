<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Events;

/**
 * تَوان | Tavaan — Workflow
 * WorkflowCompleted — فرایند تکمیل شد (`workflow.completed`).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class WorkflowCompleted
{
    public function __construct(
        public readonly string $instanceId,
        public readonly string $workflow,
        public readonly ?string $endState = null,
    ) {
    }
}
