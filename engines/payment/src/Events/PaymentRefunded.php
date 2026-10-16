<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Events;

/**
 * تَوان | Tavaan — Payment
 * PaymentRefunded — وجه بازگشت داده شد (`payment.refunded`).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PaymentRefunded
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $gateway,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $referenceId = null,
    ) {
    }
}
