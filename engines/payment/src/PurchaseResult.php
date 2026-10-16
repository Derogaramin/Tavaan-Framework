<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

/**
 * تَوان | Tavaan — Payment
 * PurchaseResult — نتیجهٔ شروع پرداخت (DOC-073 §3).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final readonly class PurchaseResult
{
    /**
     * @param string      $authority   کد رهگیری درگاه
     * @param string      $redirectUrl آدرس پرداخت
     * @param bool        $success
     * @param string|null $errorCode   کد خطای درگاه
     * @param array<string, mixed> $raw پاسخ خام
     */
    public function __construct(
        public string $authority,
        public string $redirectUrl,
        public bool $success,
        public ?string $errorCode = null,
        public array $raw = [],
    ) {
    }

    public static function success(string $authority, string $redirectUrl, array $raw = []): self
    {
        return new self($authority, $redirectUrl, true, null, $raw);
    }

    public static function failure(string $errorCode, array $raw = []): self
    {
        return new self('', '', false, $errorCode, $raw);
    }
}
