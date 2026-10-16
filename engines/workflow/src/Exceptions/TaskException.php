<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Exceptions;

/**
 * تَوان | Tavaan — Workflow
 * TaskException — خطاهای کار انسانی (claim/complete/delegate — DOC-071 §7).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class TaskException extends WorkflowException
{
    public static function notFound(string $taskId): self
    {
        return new self(
            messageId: 'workflow.task_not_found',
            context: ['task' => $taskId],
        );
    }

    public static function notPending(string $taskId, string $status): self
    {
        return new self(
            messageId: 'workflow.task_not_pending',
            context: ['task' => $taskId, 'status' => $status],
        );
    }

    public static function notClaimable(string $taskId): self
    {
        return new self(
            messageId: 'workflow.task_not_claimable',
            context: ['task' => $taskId],
            hint: 'کاربر assignee/candidate این کار نیست.',
        );
    }

    public static function noOutgoing(string $taskId, string $stateId): self
    {
        return new self(
            messageId: 'workflow.task_no_outgoing',
            context: ['task' => $taskId, 'state' => $stateId],
            hint: 'state هیچ گذار خروجی ندارد؛ transitionId را صریح بدهید.',
        );
    }

    public static function ambiguousOutgoing(string $taskId, string $stateId): self
    {
        return new self(
            messageId: 'workflow.task_ambiguous_outgoing',
            context: ['task' => $taskId, 'state' => $stateId],
            hint: 'چند گذار خروجی وجود دارد؛ transitionId را صریح بدهید.',
        );
    }
}
