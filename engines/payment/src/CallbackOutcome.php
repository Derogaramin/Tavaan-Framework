<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

/**
 * تَوان | Tavaan — Payment
 * CallbackOutcome — نتیجهٔ پردازش callback/IPN (قابل تبدیل به Response در لایهٔ وب).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final readonly class CallbackOutcome
{
    public function __construct(
        public string $transactionId,
        public PaymentStatus $status,
        public string $redirectUrl,
        /** تراکنش پیش‌تر پردازش شده بود (🔒 idempotency — verify مجدد انجام نشد) */
        public bool $alreadyProcessed = false,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status === PaymentStatus::Success
            || $this->status === PaymentStatus::AlreadyVerified;
    }
}
