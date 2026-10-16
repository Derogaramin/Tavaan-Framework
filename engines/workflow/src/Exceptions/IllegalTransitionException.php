<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Exceptions;

/**
 * تَوان | Tavaan — Workflow
 * IllegalTransitionException — گذار نامعتبر (guard رد / توکن نبود / دروازه بن‌بست).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class IllegalTransitionException extends WorkflowException
{
    /**
     * @param array<string, mixed> $context
     */
    public static function guardFailed(string $transitionId, string $from, array $context = []): self
    {
        return new self(
            messageId: 'workflow.illegal_transition',
            context: ['transition' => $transitionId, 'from' => $from, ...$context],
            hint: 'guard گذار را رد کرد؛ ورودی/متغیرهای فرایند را بررسی کنید.',
        );
    }

    public static function noToken(string $transitionId, string $from): self
    {
        return new self(
            messageId: 'workflow.illegal_transition',
            context: ['transition' => $transitionId, 'from' => $from],
            hint: 'هیچ توکن فعالی در state مبدأ وجود ندارد.',
        );
    }

    public static function unknownTransition(string $transitionId): self
    {
        return new self(
            messageId: 'workflow.unknown_transition',
            context: ['transition' => $transitionId],
        );
    }

    public static function gatewayStuck(string $gatewayId): self
    {
        return new self(
            messageId: 'workflow.gateway_stuck',
            context: ['gateway' => $gatewayId],
            hint: 'هیچ گذار خروجیِ دروازهٔ انحصاری guard را پاس نکرد.',
        );
    }

    public static function manualGatewayFire(string $gatewayId): self
    {
        return new self(
            messageId: 'workflow.gateway_manual_fire',
            context: ['gateway' => $gatewayId],
            hint: '🔒 گذارهای خروجیِ دروازه فقط توسط موتور (خودکار) اجرا می‌شوند.',
        );
    }

    public static function joinUnbalanced(string $gatewayId, int $arrived, int $expected): self
    {
        return new self(
            messageId: 'workflow.join_unbalanced',
            context: ['gateway' => $gatewayId, 'arrived' => $arrived, 'expected' => $expected],
        );
    }
}
