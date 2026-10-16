<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

use Tavaan\Engine\Database\ORM\Support\Money;

/**
 * تَوان | Tavaan — Payment
 * VerifyRequest — درخواست تأیید پرداخت (DOC-073 §3).
 *
 * 🔒 amount همیشه از Transaction محلی پر می‌شود (نه از callback کاربر).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final readonly class VerifyRequest
{
    /**
     * @param array<string, mixed> $meta دادهٔ callback (برای درگاه‌هایی که به آن نیاز دارند)
     */
    public function __construct(
        public string $authority,
        public Money $amount,
        public array $meta = [],
    ) {
    }
}
