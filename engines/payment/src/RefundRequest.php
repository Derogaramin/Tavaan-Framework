<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

use Tavaan\Engine\Database\ORM\Support\Money;

/**
 * تَوان | Tavaan — Payment
 * RefundRequest — درخواست بازگشت وجه (DOC-073 §3).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final readonly class RefundRequest
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $authority,
        public Money $amount,
        public ?string $referenceId = null,
        public array $meta = [],
    ) {
    }
}
