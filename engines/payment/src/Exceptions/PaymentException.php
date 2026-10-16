<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Payment
 * PaymentException — پایهٔ استثناهای موتور پرداخت (DOC-073).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
class PaymentException extends TavaanException
{
    public static function gatewayNotFound(string $name): self
    {
        return new self(
            messageId: 'payment.gateway_not_found',
            context: ['gateway' => $name],
        );
    }

    public static function gatewayNotConfigured(string $name, string $missing = ''): self
    {
        return new self(
            messageId: 'payment.gateway_not_configured',
            context: \array_filter(['gateway' => $name, 'missing' => $missing]),
            hint: 'اعتبارنامهٔ درگاه را در .env تنظیم کنید (🔒 هرگز هاردکد نکنید).',
        );
    }

    public static function alreadyPaid(string $transactionId): self
    {
        return new self(
            messageId: 'payment.already_paid',
            context: ['transaction' => $transactionId],
            hint: '🔒 جلوگیری از پرداخت دوباره — تراکنش پیش‌تر موفق شده است.',
        );
    }

    public static function notRefundable(string $transactionId, string $status): self
    {
        return new self(
            messageId: 'payment.not_refundable',
            context: ['transaction' => $transactionId, 'status' => $status],
        );
    }

    public static function unsupported(string $gateway, string $operation): self
    {
        return new self(
            messageId: 'payment.unsupported_operation',
            context: ['gateway' => $gateway, 'operation' => $operation],
        );
    }

    public static function invalidAmount(string $reason = ''): self
    {
        return new self(
            messageId: 'payment.invalid_amount',
            context: \array_filter(['reason' => $reason]),
        );
    }

    public static function orderIdRequired(): self
    {
        return new self(
            messageId: 'payment.order_id_required',
            hint: 'orderId برای idempotency و مطابقت سفارش الزامی است.',
        );
    }
}
