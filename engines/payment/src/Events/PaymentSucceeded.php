<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Events;

/**
 * تَوان | Tavaan — Payment
 * PaymentSucceeded — پرداخت تأیید شد (`payment.succeeded`).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PaymentSucceeded
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $gateway,
        public readonly ?string $referenceId = null,
        public readonly int $amount = 0,
        public readonly string $currency = 'IRT',
        public readonly ?string $orderType = null,
        public readonly ?string $orderId = null,
    ) {
    }
}
