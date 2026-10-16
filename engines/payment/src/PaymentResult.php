<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

use Tavaan\Engine\Database\ORM\Support\Money;

/**
 * تَوان | Tavaan — Payment
 * PaymentResult — نتیجهٔ verify (DOC-073 §3).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final readonly class PaymentResult
{
    /**
     * @param PaymentStatus $status
     * @param string|null   $referenceId کد رهگیری بانک
     * @param string|null   $cardPan     🔒 mask‌شده (۶ اول + ۴ آخر)
     * @param array<string, mixed> $raw  پاسخ خام درگاه
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $referenceId,
        public ?string $cardPan,
        public Money $amount,
        public string $authority,
        public array $raw = [],
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status === PaymentStatus::Success;
    }

    public function isAlreadyVerified(): bool
    {
        return $this->status === PaymentStatus::AlreadyVerified;
    }
}
