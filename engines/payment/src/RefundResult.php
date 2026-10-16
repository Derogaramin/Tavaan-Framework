<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

/**
 * تَوان | Tavaan — Payment
 * RefundResult — نتیجهٔ بازگشت وجه (DOC-073 §3).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final readonly class RefundResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public bool $success,
        public ?string $referenceId = null,
        public ?string $errorCode = null,
        public array $raw = [],
    ) {
    }

    public static function ok(?string $referenceId = null, array $raw = []): self
    {
        return new self(true, $referenceId, null, $raw);
    }

    public static function fail(string $errorCode, array $raw = []): self
    {
        return new self(false, null, $errorCode, $raw);
    }
}
