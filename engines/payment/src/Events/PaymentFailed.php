<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Events;

/**
 * تَوان | Tavaan — Payment
 * PaymentFailed — پرداخت ناموفق/لغوشده (`payment.failed`).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PaymentFailed
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $gateway,
        public readonly string $reason = '',
        public readonly int $amount = 0,
        public readonly string $currency = 'IRT',
        public readonly ?string $orderId = null,
    ) {
    }
}
