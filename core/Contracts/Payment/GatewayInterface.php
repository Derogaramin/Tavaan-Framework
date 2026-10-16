<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Payment;

use Tavaan\Engine\Payment\PaymentResult;
use Tavaan\Engine\Payment\PaymentStatus;
use Tavaan\Engine\Payment\PurchaseRequest;
use Tavaan\Engine\Payment\PurchaseResult;
use Tavaan\Engine\Payment\RefundRequest;
use Tavaan\Engine\Payment\RefundResult;
use Tavaan\Engine\Payment\VerifyRequest;

/**
 * تَوان | Tavaan — Contracts
 * GatewayInterface — قرارداد مشترک درگاه‌های پرداخت (DOC-073 §3).
 *
 * 🔒 AC-PAY-10: افزودن درگاه جدید فقط با پیاده‌سازی این قرارداد.
 * 🔒 verify همیشه با مبلغ Transaction محلی صدا زده می‌شود (نه مبلغ callback).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
interface GatewayInterface
{
    /** شروع پرداخت → authority + redirect URL. */
    public function purchase(PurchaseRequest $request): PurchaseResult;

    /** تأیید پس از بازگشت کاربر (server-to-server با API درگاه). */
    public function verify(VerifyRequest $request): PaymentResult;

    /** بازگشت وجه (کامل یا جزئی). */
    public function refund(RefundRequest $request): RefundResult;

    /** استعلام وضعیت تراکنش. */
    public function inquire(string $authority): PaymentStatus;

    /** نام درگاه (کلید config). */
    public function name(): string;
}
