<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Events;

/**
 * تَوان | Tavaan — Payment
 * PaymentInitiated — تراکنش آغاز شد و کاربر باید به درگاه برود (`payment.initiated`).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PaymentInitiated
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $gateway,
        public readonly string $authority,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $orderType = null,
        public readonly ?string $orderId = null,
    ) {
    }
}
