<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Events;

/**
 * تَوان | Tavaan — Workflow
 * WorkflowStarted — فرایند شروع شد (`workflow.started`).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class WorkflowStarted
{
    public function __construct(
        public readonly string $instanceId,
        public readonly string $workflow,
        public readonly ?string $subjectType = null,
        public readonly ?string $subjectId = null,
    ) {
    }
}
