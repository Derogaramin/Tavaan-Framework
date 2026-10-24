<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * EnginePaymentGateway — آداپتر موتور payment (درگاه‌های واقعی ایرانی).
 */

namespace Modules\Shop\Infrastructure\Gateways;

use Modules\Shop\Contracts\PaymentGatewayPort;
use Tavaan\Core\Facade\Facades\Payment;

/**
 * EnginePaymentGateway — پلی بین پورت ماژول و موتور payment (M7).
 *
 * 🔒 ARCH-003: فقط Facade هسته؛ نتایج به‌صورت dynamic خوانده می‌شوند
 * (بدون type-hint به کلاس‌های موتور).
 * 🔒 verify همیشه با مبلغ سفارش محلی صدا زده می‌شود (DOC-120 §5:
 * «verify از API، نه callback»).
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class EnginePaymentGateway implements PaymentGatewayPort
{
    public function __construct(private readonly string $gateway = '')
    {
    }

    public function purchase(int $amountMinor, string $currency, string $orderNumber, string $callbackUrl, ?string $mobile = null, ?string $email = null, array $meta = []): array
    {
        $result = Payment::purchase([
            'amount' => $amountMinor,
            'currency' => $currency,
            'callback_url' => $callbackUrl,
            'mobile' => (string) $mobile,
            'email' => $email,
            'description' => 'سفارش ' . $orderNumber,
            'order_id' => $orderNumber,
            'meta' => $meta,
        ], $this->gatewayOrNull());

        if (!\is_object($result)) {
            throw new \RuntimeException('purchase ناموفق — نتیجهٔ درگاه نامعتبر.');
        }

        $authority = (string) ($result->authority ?? '');
        $redirectUrl = (string) ($result->redirectUrl ?? ($result->redirect_url ?? ''));
        if ($authority === '') {
            $reason = \property_exists($result, 'message') ? (string) $result->message : 'نامعلوم';
            throw new \RuntimeException('درگاه authority برنگرداند: ' . $reason);
        }

        return ['authority' => $authority, 'redirect_url' => $redirectUrl];
    }

    public function verify(string $authority, int $expectedAmountMinor, string $currency, array $callbackMeta = []): bool
    {
        $result = Payment::verify($authority, $this->gatewayOrNull(), $callbackMeta);

        return \is_object($result) && \method_exists($result, 'isSuccess') && $result->isSuccess();
    }

    public function refund(string $authority, int $amountMinor, string $currency): bool
    {
        $transaction = Payment::findByAuthority($authority, $this->gatewayOrNull());
        if ($transaction === null) {
            return false;
        }

        $result = Payment::refund($transaction);

        if (\is_object($result) && \property_exists($result, 'success')) {
            return (bool) $result->success;
        }

        return \is_object($result) && \method_exists($result, 'isSuccess') && $result->isSuccess();
    }

    public function name(): string
    {
        return 'engine:' . ($this->gateway !== '' ? $this->gateway : 'default');
    }

    private function gatewayOrNull(): ?string
    {
        return $this->gateway !== '' ? $this->gateway : null;
    }
}
