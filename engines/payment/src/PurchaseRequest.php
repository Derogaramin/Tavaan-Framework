<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

use Tavaan\Engine\Database\ORM\Support\Money;

/**
 * تَوان | Tavaan — Payment
 * PurchaseRequest — درخواست شروع پرداخت (DOC-073 §3).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final readonly class PurchaseRequest
{
    /**
     * @param Money               $amount      مبلغ (💰 Money — هرگز float؛ ADR-0013)
     * @param string              $callbackUrl آدرس بازگشت
     * @param string              $mobile      موبایل کاربر (اختیاری در بیشتر درگاه‌ها)
     * @param array<string, mixed> $meta       دادهٔ اضافی (user_id و …)
     */
    public function __construct(
        public Money $amount,
        public string $callbackUrl,
        public string $mobile = '',
        public ?string $email = null,
        public ?string $description = null,
        public string $orderId = '',
        public array $meta = [],
    ) {
    }

    /**
     * ساخت از آرایه (کلیدهای snake_case).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $defaultCurrency = 'IRT'): self
    {
        $amount = $data['amount'] ?? null;
        $money = $amount instanceof Money
            ? $amount
            : Money::of((int) $amount, (string) ($data['currency'] ?? $defaultCurrency));

        return new self(
            amount: $money,
            callbackUrl: (string) ($data['callback_url'] ?? ''),
            mobile: (string) ($data['mobile'] ?? ''),
            email: isset($data['email']) ? (string) $data['email'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            orderId: (string) ($data['order_id'] ?? ''),
            meta: (array) ($data['meta'] ?? []),
        );
    }
}
