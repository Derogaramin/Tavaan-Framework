<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Events;

/**
 * تَوان | Tavaan — Workflow
 * ProcessTransitioned — یک گذار اجرا شد (`workflow.transitioned`).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class ProcessTransitioned
{
    public function __construct(
        public readonly string $instanceId,
        public readonly string $transition,
        public readonly string $from,
        public readonly string $to,
        /** user|timer|subprocess */
        public readonly string $trigger = 'user',
    ) {
    }
}
