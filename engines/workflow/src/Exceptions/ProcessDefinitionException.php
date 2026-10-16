<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Exceptions;

/**
 * تَوان | Tavaan — Workflow
 * ProcessDefinitionException — خطای ساختاری در تعریف فرایند (DOC-071 §9 T-01).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class ProcessDefinitionException extends WorkflowException
{
    public static function missingStart(string $processId): self
    {
        return new self(
            messageId: 'workflow.definition.missing_start',
            context: ['process' => $processId],
            hint: 'هر فرایند باید دقیقاً یک state با type=start داشته باشد.',
        );
    }

    public static function duplicateState(string $processId, string $stateId): self
    {
        return new self(
            messageId: 'workflow.definition.duplicate_state',
            context: ['process' => $processId, 'state' => $stateId],
        );
    }

    public static function duplicateTransition(string $processId, string $transitionId): self
    {
        return new self(
            messageId: 'workflow.definition.duplicate_transition',
            context: ['process' => $processId, 'transition' => $transitionId],
        );
    }

    public static function unknownState(string $processId, string $stateId, string $where): self
    {
        return new self(
            messageId: 'workflow.definition.unknown_state',
            context: ['process' => $processId, 'state' => $stateId, 'where' => $where],
        );
    }

    public static function invalidId(string $processId, string $id): self
    {
        return new self(
            messageId: 'workflow.definition.invalid_id',
            context: ['process' => $processId, 'id' => $id],
            hint: 'شناسه باید غیرخالی و بدون فضای خالی باشد.',
        );
    }

    public static function orphanTransition(string $processId, string $stateId): self
    {
        return new self(
            messageId: 'workflow.definition.orphan_state',
            context: ['process' => $processId, 'state' => $stateId],
            hint: 'state غیر start/end بدون هیچ گذار ورودی یا خروجی است.',
        );
    }

    public static function missingProcess(string $processId, string $stateId): self
    {
        return new self(
            messageId: 'workflow.definition.subprocess_missing',
            context: ['process' => $processId, 'state' => $stateId],
        );
    }
}
