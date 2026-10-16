<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Workflow
 * WorkflowException — پایهٔ استثناهای موتور گردش‌کار (DOC-071 / DOC-013).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
class WorkflowException extends TavaanException
{
    public static function invalidDuration(string $after): self
    {
        return new self(
            messageId: 'workflow.invalid_duration',
            context: ['after' => $after],
            hint: 'مدت باید ISO-8601 (مثل P3D) یا انسانی (مثل "3 days") باشد.',
        );
    }

    public static function workflowNotFound(string $name): self
    {
        return new self(
            messageId: 'workflow.not_found',
            context: ['workflow' => $name],
        );
    }

    public static function processNotFound(string $id): self
    {
        return new self(
            messageId: 'workflow.process_not_found',
            context: ['instance' => $id],
        );
    }

    public static function notRunning(string $instanceId, string $status): self
    {
        return new self(
            messageId: 'workflow.not_running',
            context: ['instance' => $instanceId, 'status' => $status],
            hint: 'فقط instance با status=running می‌تواند گذار انجام دهد.',
        );
    }

    public static function maxInstances(string $workflow, int $limit): self
    {
        return new self(
            messageId: 'workflow.max_instances',
            context: ['workflow' => $workflow, 'limit' => $limit],
            hint: '🔒 محافظ DoS: سقف instance فعال در config/workflow.php.',
        );
    }

    public static function timerNotFound(string $instanceId, string $timerId): self
    {
        return new self(
            messageId: 'workflow.timer_not_found',
            context: ['instance' => $instanceId, 'timer' => $timerId],
        );
    }
}
