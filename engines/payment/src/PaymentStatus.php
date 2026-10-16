<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

/**
 * تَوان | Tavaan — Payment
 * PaymentStatus — وضعیت تراکنش پرداخت (DOC-073 §3).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
enum PaymentStatus: string
{
    /** در انتظار پرداخت/تأیید */
    case Pending = 'pending';

    /** موفق */
    case Success = 'success';

    /** ناموفق */
    case Failed = 'failed';

    /** لغوشده توسط کاربر/درگاه */
    case Canceled = 'canceled';

    /** بازگشت داده شده */
    case Refunded = 'refunded';

    /** پیش‌تر verify شده (کد ۱۰۱ زرین‌پال و مشابه) */
    case AlreadyVerified = 'already_verified';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    public function isPaid(): bool
    {
        return $this === self::Success || $this === self::AlreadyVerified;
    }
}
