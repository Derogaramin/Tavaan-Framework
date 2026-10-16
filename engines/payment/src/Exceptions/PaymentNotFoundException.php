<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Exceptions;

/**
 * تَوان | Tavaan — Payment
 * PaymentNotFoundException — تراکنش با authority/شناسهٔ داده‌شده یافت نشد.
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PaymentNotFoundException extends PaymentException
{
    public static function byAuthority(string $authority): self
    {
        return new self(
            messageId: 'payment.not_found',
            context: ['authority' => $authority],
        );
    }

    public static function byId(string $id): self
    {
        return new self(
            messageId: 'payment.not_found',
            context: ['transaction' => $id],
        );
    }
}
