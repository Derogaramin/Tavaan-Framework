<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * PaymentGatewayPort — پورت درگاه پرداخت ماژول (Hexagonal).
 */

namespace Modules\Lms\Contracts;

/**
 * PaymentGatewayPort — مرز ماژول با دنیای پرداخت (ثبت‌نام دورهٔ پولی — DOC-095 §3.1).
 *
 * دو آداپتر: `SandboxPaymentGateway` (dev/test/demo — قطعی و بدون شبکه)
 * و `EnginePaymentGateway` (درگاه‌های واقعی موتور payment از راه Facade).
 *
 * 🔒 همان الگوی ماژول shop (TECH-DEBT-ARCH-01): قرارداد موتور به VOهای
 * موتور تایپ شده و ماژول نمی‌تواند به `Tavaan\Engine\*` ارجاع دهد (ARCH-003).
 *
 * @api 1.0
 */
interface PaymentGatewayPort
{
    /**
     * شروع پرداخت.
     *
     * @param array<string, mixed> $meta
     *
     * @return array{authority: string, redirect_url: string}
     */
    public function purchase(int $amountMinor, string $currency, string $reference, string $callbackUrl, ?string $mobile = null, ?string $email = null, array $meta = []): array;

    /**
     * تأیید پرداخت (server-to-server) — DOC-120 §5: verify از API، نه callback.
     *
     * @param array<string, mixed> $callbackMeta
     */
    public function verify(string $authority, int $expectedAmountMinor, string $currency, array $callbackMeta = []): bool;

    /** استرداد (کامل) — انصراف پس از پرداخت. */
    public function refund(string $authority, int $amountMinor, string $currency): bool;

    /** نام درایور (sandbox|engine:<gateway>). */
    public function name(): string;
}
