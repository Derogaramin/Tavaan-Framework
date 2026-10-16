<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Exceptions;

/**
 * تَوان | Tavaan — Payment
 * GatewayException — خطای ارتباط/پاسخ نامعتبر از سوی درگاه.
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class GatewayException extends PaymentException
{
    /**
     * @param array<string, mixed> $raw
     */
    public static function response(string $gateway, string $operation, int $status = 0, array $raw = []): self
    {
        return new self(
            messageId: 'payment.gateway_error',
            context: ['gateway' => $gateway, 'operation' => $operation, 'status' => $status],
            hint: \json_encode($raw, JSON_UNESCAPED_UNICODE) ?: null,
        );
    }

    public static function connection(string $gateway, string $operation, \Throwable $previous): self
    {
        return new self(
            messageId: 'payment.gateway_connection_failed',
            context: ['gateway' => $gateway, 'operation' => $operation],
            hint: $previous->getMessage(),
            previous: $previous,
        );
    }
}
