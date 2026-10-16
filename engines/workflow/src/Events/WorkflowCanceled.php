<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Events;

/**
 * تَوان | Tavaan — Workflow
 * WorkflowCanceled — فرایند لغو شد (`workflow.canceled`).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class WorkflowCanceled
{
    public function __construct(
        public readonly string $instanceId,
        public readonly string $workflow,
    ) {
    }
}
